<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class CheckOperationalHealth extends Command
{
    protected $signature = 'operations:health
        {--skip-heartbeat : Only check application/database/migration health - use this right after a fresh deploy, before the scheduler has had a chance to tick, so a stale heartbeat does not fail the check.}';

    protected $description = 'Check application/database/migration health and whether the Laravel scheduler and queue worker heartbeats are current';

    public function handle(OperationalHealth $health): int
    {
        [$appHealthy, $appRows] = $this->appHealth();

        $heartbeatStatus = $health->status();
        $heartbeatRows = collect($heartbeatStatus)
            ->map(fn (array $component, string $name): array => [
                $name,
                $component['healthy'] ? 'OK' : 'STALE',
                $component['last_seen'] ?? 'belum pernah',
            ])
            ->values()
            ->all();

        $this->table(['Komponen', 'Status', 'Detail'], [...$appRows, ...$heartbeatRows]);

        $heartbeatUnhealthy = collect($heartbeatStatus)->contains(fn (array $component): bool => ! $component['healthy']);
        $skippingHeartbeat = (bool) $this->option('skip-heartbeat');

        if (! $appHealthy) {
            $this->error('Aplikasi/database/migrasi bermasalah. Lihat tabel di atas.');

            return self::FAILURE;
        }

        if ($heartbeatUnhealthy && $skippingHeartbeat) {
            $this->warn('Scheduler/worker belum pernah tick atau sudah lama - wajar segera setelah deploy, dilewati karena --skip-heartbeat.');
            $this->info('Aplikasi/database/migrasi sehat.');

            return self::SUCCESS;
        }

        if ($heartbeatUnhealthy) {
            $this->error('Scheduler atau queue worker tidak aktif/terlambat.');

            return self::FAILURE;
        }

        $this->info('Semua komponen sehat: aplikasi, database, migrasi, scheduler, dan queue worker.');

        return self::SUCCESS;
    }

    /** @return array{0: bool, 1: list<array{string, string, string}>} */
    private function appHealth(): array
    {
        $rows = [];
        $healthy = true;

        try {
            DB::select('select 1');
            $rows[] = ['database', 'OK', 'Koneksi database berhasil.'];
        } catch (Throwable $e) {
            $rows[] = ['database', 'GAGAL', Str::limit($e->getMessage(), 100)];
            $healthy = false;
        }

        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(File::exists(database_path('migrations')) ? File::files(database_path('migrations')) : [])
                ->map(fn ($file): string => basename($file->getFilename(), '.php'))
                ->values()
                ->all();
            $pending = array_values(array_diff($files, $ran));

            if (count($pending) > 0) {
                $rows[] = ['migrations', 'PENDING', count($pending).' migrasi belum dijalankan (mis. '.$pending[0].').'];
                $healthy = false;
            } else {
                $rows[] = ['migrations', 'OK', count($files).' migrasi, semuanya sudah dijalankan.'];
            }
        } catch (Throwable $e) {
            $rows[] = ['migrations', 'GAGAL', Str::limit($e->getMessage(), 100)];
            $healthy = false;
        }

        return [$healthy, $rows];
    }
}
