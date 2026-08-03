<?php

namespace App\Jobs;

use App\Services\Reports\TemporaryReportFileCleanup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class CleanupTemporaryReportFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TemporaryReportFileCleanup $cleanup): int
    {
        $directory = (string) config('reports.temporary_directory');

        if (! File::isDirectory($directory)) {
            return 0;
        }

        $cutoff = now()->subMinutes(
            max(1, (int) config('reports.temporary_file_grace_minutes', 60)),
        )->getTimestamp();
        $cleaned = 0;

        foreach (File::files($directory) as $file) {
            if (! str_starts_with($file->getFilename(), 'profit-loss-')
                || $file->getMTime() > $cutoff) {
                continue;
            }

            if ($cleanup->delete($file->getPathname())) {
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
