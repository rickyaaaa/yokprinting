<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Log;
use Throwable;

class TemporaryReportFileCleanup
{
    public function delete(string $path): bool
    {
        if (! is_file($path)) {
            return true;
        }

        try {
            if (! $this->removeFile($path)) {
                Log::warning('Temporary report file cleanup failed.', [
                    'path_hash' => hash('sha256', $path),
                    'error_type' => 'unlink_returned_false',
                ]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Temporary report file cleanup failed.', [
                'path_hash' => hash('sha256', $path),
                'error_type' => $exception::class,
            ]);

            return false;
        }
    }

    protected function removeFile(string $path): bool
    {
        return unlink($path);
    }
}
