<?php

namespace App\Services\Expenses;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExpenseProofCleanup
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function queue(string $path, string $reason, ?int $expenseId = null, ?string $disk = null): ExpenseProofCleanupTask
    {
        $disk ??= (string) config('expenses.proof_disk', 'expense_proofs');

        return ExpenseProofCleanupTask::query()->updateOrCreate(
            ['disk' => $disk, 'path' => $path],
            [
                'expense_id' => $expenseId,
                'reason' => $reason,
                'next_attempt_at' => now(),
            ],
        );
    }

    public function cleanup(string $path, string $reason, ?int $expenseId = null, ?string $disk = null): bool
    {
        return $this->attempt($this->queue($path, $reason, $expenseId, $disk));
    }

    public function attempt(ExpenseProofCleanupTask $task): bool
    {
        try {
            $disk = Storage::disk($task->disk);

            if ($disk->exists($task->path) && ! $disk->delete($task->path)) {
                throw new RuntimeException('Storage driver returned false while deleting an expense proof.');
            }

            $this->recordEvent($task, 'proof_cleanup_succeeded', 'Expense proof cleanup succeeded');
            $task->delete();

            return true;
        } catch (Throwable $exception) {
            $attempts = $task->attempts + 1;
            $retryMinutes = max(1, (int) config('expenses.cleanup_retry_minutes', 15));

            $task->forceFill([
                'attempts' => $attempts,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'next_attempt_at' => now()->addMinutes(min($retryMinutes * $attempts, 1440)),
            ])->save();

            Log::error('Expense proof cleanup failed and was queued for retry.', [
                'cleanup_task_id' => $task->getKey(),
                'expense_id' => $task->expense_id,
                'reason' => $task->reason,
                'attempts' => $attempts,
                'exception' => $exception,
            ]);
            $this->recordEvent($task, 'proof_cleanup_failed', 'Expense proof cleanup failed', $exception->getMessage());

            return false;
        }
    }

    private function recordEvent(
        ExpenseProofCleanupTask $task,
        string $action,
        string $event,
        ?string $error = null,
    ): void {
        try {
            $expense = $task->expense_id
                ? Expense::withTrashed()->find($task->expense_id)
                : null;

            $this->activityLogger->record(
                module: 'expense',
                action: $action,
                event: $event,
                description: $error === null
                    ? 'Pembersihan bukti pengeluaran selesai.'
                    : 'Pembersihan bukti pengeluaran gagal dan dijadwalkan ulang.',
                subject: $expense,
                metadata: [
                    'cleanup_task_id' => $task->getKey(),
                    'reason' => $task->reason,
                    'attempts' => $task->attempts,
                    'error' => $error === null ? null : mb_substr($error, 0, 500),
                ],
                riskLevel: $error === null ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_HIGH,
            );
        } catch (Throwable $auditException) {
            Log::error('Failed to record expense proof cleanup audit event.', [
                'cleanup_task_id' => $task->getKey(),
                'action' => $action,
                'exception' => $auditException,
            ]);
        }
    }
}
