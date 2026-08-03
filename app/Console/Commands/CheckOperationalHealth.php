<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalHealth;
use Illuminate\Console\Command;

class CheckOperationalHealth extends Command
{
    protected $signature = 'operations:health';

    protected $description = 'Check whether the Laravel scheduler and queue worker heartbeats are current';

    public function handle(OperationalHealth $health): int
    {
        $status = $health->status();

        $this->table(
            ['Komponen', 'Status', 'Terakhir terlihat'],
            collect($status)->map(fn (array $component, string $name): array => [
                $name,
                $component['healthy'] ? 'OK' : 'STALE',
                $component['last_seen'] ?? 'belum pernah',
            ])->values()->all(),
        );

        if (collect($status)->contains(fn (array $component): bool => ! $component['healthy'])) {
            $this->error('Scheduler atau queue worker tidak aktif/terlambat.');

            return self::FAILURE;
        }

        $this->info('Scheduler dan queue worker aktif.');

        return self::SUCCESS;
    }
}
