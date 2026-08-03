<?php

namespace App\Services\Operations;

use App\Jobs\UpdateQueueWorkerHeartbeatJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class OperationalHealth
{
    private const SCHEDULER_KEY = 'operations.health.scheduler.last_seen';

    private const WORKER_KEY = 'operations.health.worker.last_seen';

    public function schedulerTick(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->toISOString());
        UpdateQueueWorkerHeartbeatJob::dispatch();
    }

    public function workerTick(): void
    {
        Cache::forever(self::WORKER_KEY, now()->toISOString());
    }

    /** @return array<string, array{last_seen: ?string, healthy: bool}> */
    public function status(): array
    {
        $maxAge = max(1, (int) config('operations.health_max_age_minutes', 5));

        return [
            'scheduler' => $this->componentStatus(Cache::get(self::SCHEDULER_KEY), $maxAge),
            'worker' => $this->componentStatus(Cache::get(self::WORKER_KEY), $maxAge),
        ];
    }

    /** @return array{last_seen: ?string, healthy: bool} */
    private function componentStatus(mixed $lastSeen, int $maxAge): array
    {
        if (! is_string($lastSeen) || $lastSeen === '') {
            return ['last_seen' => null, 'healthy' => false];
        }

        $timestamp = CarbonImmutable::parse($lastSeen, (string) config('app.timezone', 'UTC'));

        return [
            'last_seen' => $timestamp->toISOString(),
            'healthy' => $timestamp->greaterThanOrEqualTo(now()->subMinutes($maxAge)),
        ];
    }
}
