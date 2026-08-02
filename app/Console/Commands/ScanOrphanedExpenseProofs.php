<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Services\Expenses\ExpenseProofCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanOrphanedExpenseProofs extends Command
{
    protected $signature = 'expenses:proofs:scan
        {--delete : Explicitly delete orphaned files through the retryable cleanup workflow}
        {--grace-minutes= : Override the configured minimum file age}';

    protected $description = 'Report expense proof files that have no expense or cleanup-task reference';

    public function handle(ExpenseProofCleanup $cleanup): int
    {
        $graceMinutes = $this->graceMinutes();

        if ($graceMinutes === null) {
            return self::FAILURE;
        }

        $diskName = (string) config('expenses.proof_disk', 'expense_proofs');
        $disk = Storage::disk($diskName);
        $referenced = Expense::withTrashed()->pluck('proof_path')
            ->merge(ExpenseProofCleanupTask::query()->where('disk', $diskName)->pluck('path'))
            ->filter()
            ->flip();
        $cutoff = now()->subMinutes($graceMinutes)->getTimestamp();
        $orphans = [];
        $inspectionFailures = 0;

        foreach ($disk->allFiles('expense-proofs') as $path) {
            if ($referenced->has($path)) {
                continue;
            }

            try {
                if ($disk->lastModified($path) > $cutoff) {
                    continue;
                }
            } catch (Throwable) {
                $inspectionFailures++;
                $this->warn('Satu file dilewati karena metadata waktunya tidak dapat dibaca.');

                continue;
            }

            $orphans[] = $path;
        }

        if ($orphans === []) {
            $this->info('Tidak ada bukti pengeluaran yatim melewati grace period.');

            return $inspectionFailures === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Bukti yatim'], array_map(fn (string $path): array => [$path], $orphans));

        if (! $this->option('delete')) {
            $this->warn(count($orphans).' file hanya dilaporkan. Jalankan ulang dengan --delete untuk menghapus secara eksplisit.');

            return $inspectionFailures === 0 ? self::SUCCESS : self::FAILURE;
        }

        $failed = 0;

        foreach ($orphans as $path) {
            if (! $cleanup->cleanup($path, 'orphan_scan', disk: $diskName)) {
                $failed++;
            }
        }

        $this->info((count($orphans) - $failed).' file yatim berhasil dibersihkan.');

        if ($failed > 0) {
            $this->warn("{$failed} file dipertahankan dalam cleanup task untuk retry.");
        }

        return ($failed === 0 && $inspectionFailures === 0) ? self::SUCCESS : self::FAILURE;
    }

    private function graceMinutes(): ?int
    {
        $value = $this->option('grace-minutes');
        $value = $value === null || $value === ''
            ? config('expenses.orphan_scan_grace_minutes', 1440)
            : $value;

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            $this->error('Grace period harus berupa bilangan bulat nol atau lebih.');

            return null;
        }

        return (int) $value;
    }
}
