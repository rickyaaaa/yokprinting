<?php

namespace App\Services\Expenses;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Services\Security\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExpenseProofCleanup
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function queue(string $path, string $reason, ?int $expenseId = null, ?string $disk = null): ExpenseProofCleanupTask
    {
        $disk ??= (string) config('expenses.proof_disk', 'expense_proofs');

        return ExpenseProofCleanupTask::query()->firstOrCreate(
            ['disk' => $disk, 'path' => $path],
            [
                'expense_id' => $expenseId,
                'reason' => $reason,
                'next_attempt_at' => now(),
                'status' => ExpenseProofCleanupTask::STATUS_PENDING,
            ],
        );
    }

    public function cleanup(string $path, string $reason, ?int $expenseId = null, ?string $disk = null): bool
    {
        return $this->attempt($this->queue($path, $reason, $expenseId, $disk));
    }

    public function attempt(ExpenseProofCleanupTask $task): bool
    {
        $claimed = $this->claim($task->getKey());

        if (! $claimed) {
            return false;
        }

        return $this->attemptClaimed($claimed);
    }

    public function claim(int $taskId): ?ExpenseProofCleanupTask
    {
        $now = now();
        $staleBefore = $now->copy()->subMinutes(
            max(1, (int) config('expenses.cleanup_claim_timeout_minutes', 30)),
        );
        $token = (string) Str::uuid();

        $claimed = ExpenseProofCleanupTask::query()
            ->whereKey($taskId)
            ->where(function (Builder $query) use ($now, $staleBefore): void {
                $query
                    ->where(function (Builder $pending) use ($now): void {
                        $pending
                            ->where('status', ExpenseProofCleanupTask::STATUS_PENDING)
                            ->where(fn (Builder $due): Builder => $due
                                ->whereNull('next_attempt_at')
                                ->orWhere('next_attempt_at', '<=', $now));
                    })
                    ->orWhere(function (Builder $processing) use ($staleBefore): void {
                        $processing
                            ->where('status', ExpenseProofCleanupTask::STATUS_PROCESSING)
                            ->where(fn (Builder $stale): Builder => $stale
                                ->whereNull('claimed_at')
                                ->orWhere('claimed_at', '<=', $staleBefore));
                    });
            })
            ->update([
                'status' => ExpenseProofCleanupTask::STATUS_PROCESSING,
                'claim_token' => $token,
                'claimed_at' => $now,
                'updated_at' => $now,
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return ExpenseProofCleanupTask::query()
            ->whereKey($taskId)
            ->where('claim_token', $token)
            ->first();
    }

    private function attemptClaimed(ExpenseProofCleanupTask $task): bool
    {
        try {
            $disk = Storage::disk($task->disk);

            if ($disk->exists($task->path) && ! $disk->delete($task->path)) {
                throw new RuntimeException('Storage driver returned false while deleting an expense proof.');
            }

            DB::transaction(function () use ($task): void {
                if (! $this->recordEvent($task, 'proof_cleanup_succeeded', 'Expense proof cleanup succeeded')) {
                    throw new RuntimeException('Expense proof was deleted but its success audit could not be persisted.');
                }

                $deleted = ExpenseProofCleanupTask::query()
                    ->whereKey($task->getKey())
                    ->where('claim_token', $task->claim_token)
                    ->delete();

                if ($deleted !== 1) {
                    throw new RuntimeException('Expense proof cleanup claim was lost before completion.');
                }
            });

            return true;
        } catch (Throwable $exception) {
            $attempts = $task->attempts + 1;
            $retryMinutes = max(1, (int) config('expenses.cleanup_retry_minutes', 15));

            $task->forceFill([
                'attempts' => $attempts,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'next_attempt_at' => now()->addMinutes(min($retryMinutes * $attempts, 1440)),
                'status' => ExpenseProofCleanupTask::STATUS_PENDING,
                'claim_token' => null,
                'claimed_at' => null,
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
    ): bool {
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
                    'expense_id' => $task->expense_id,
                    'reason' => $task->reason,
                    'attempts' => $task->attempts,
                    'error' => $error === null ? null : mb_substr($error, 0, 500),
                ],
                riskLevel: $error === null ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_HIGH,
            );

            return true;
        } catch (Throwable $auditException) {
            Log::error('Failed to record expense proof cleanup audit event.', [
                'cleanup_task_id' => $task->getKey(),
                'action' => $action,
                'exception' => $auditException,
            ]);

            return false;
        }
    }
}
