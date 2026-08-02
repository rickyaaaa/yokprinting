<?php

namespace App\Jobs;

use App\Models\ExpenseProofCleanupTask;
use App\Services\Expenses\ExpenseProofCleanup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryExpenseProofCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpenseProofCleanup $cleanup): int
    {
        $processed = 0;

        ExpenseProofCleanupTask::query()
            ->where(fn ($query) => $query
                ->whereNull('next_attempt_at')
                ->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit(max(1, (int) config('expenses.cleanup_batch_size', 100)))
            ->get()
            ->each(function (ExpenseProofCleanupTask $task) use ($cleanup, &$processed): void {
                $cleanup->attempt($task);
                $processed++;
            });

        return $processed;
    }
}
