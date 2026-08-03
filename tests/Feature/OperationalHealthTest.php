<?php

namespace Tests\Feature;

use App\Jobs\UpdateQueueWorkerHeartbeatJob;
use App\Services\Operations\OperationalHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['operations.health_max_age_minutes' => 5]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_health_command_detects_missing_and_current_scheduler_worker_heartbeats(): void
    {
        $this->artisan('operations:health')
            ->expectsOutputToContain('tidak aktif/terlambat')
            ->assertFailed();

        Queue::fake();
        $health = app(OperationalHealth::class);
        $health->schedulerTick();
        Queue::assertPushed(UpdateQueueWorkerHeartbeatJob::class);
        (new UpdateQueueWorkerHeartbeatJob)->handle($health);

        $this->artisan('operations:health')
            ->expectsOutputToContain('Scheduler dan queue worker aktif.')
            ->assertSuccessful();

        Carbon::setTestNow(now()->addMinutes(6));
        $this->artisan('operations:health')->assertFailed();
    }
}
