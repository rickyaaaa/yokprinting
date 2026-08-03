<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Services\Expenses\ExpenseProofCleanup;
use App\Services\Security\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PurgeExpiredExpensesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpenseProofCleanup $cleanup, ActivityLogger $activityLogger): int
    {
        $retentionDays = max(0, (int) config('expenses.proof_retention_days', 365));
        $cutoff = now()->subDays($retentionDays);
        $purged = 0;

        Expense::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($expenses) use ($cleanup, $activityLogger, $cutoff, &$purged): void {
                foreach ($expenses as $expense) {
                    $cleanupTask = DB::transaction(function () use ($expense, $cleanup, $activityLogger, $cutoff): ?ExpenseProofCleanupTask {
                        $locked = Expense::withTrashed()->lockForUpdate()->find($expense->getKey());

                        if (! $locked?->trashed() || $locked->deleted_at->isAfter($cutoff)) {
                            return null;
                        }

                        $task = $cleanup->queue($locked->proof_path, 'retention_purge', $locked->getKey());

                        $activityLogger->record(
                            module: 'expense',
                            action: 'purge_queued',
                            event: 'Expense purge committed',
                            description: 'Record pengeluaran dihapus permanen dan cleanup bukti dijadwalkan.',
                            subject: $locked,
                            metadata: [
                                'deleted_at' => $locked->deleted_at->toISOString(),
                                'cleanup_task_id' => $task->getKey(),
                            ],
                            riskLevel: ActivityLog::RISK_HIGH,
                        );
                        $locked->forceDelete();

                        return $task;
                    });

                    if ($cleanupTask instanceof ExpenseProofCleanupTask) {
                        $purged++;
                        $cleanup->attempt($cleanupTask);
                    }
                }
            });

        return $purged;
    }
}
