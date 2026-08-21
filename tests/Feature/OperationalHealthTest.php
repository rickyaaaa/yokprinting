<?php

namespace Tests\Feature;

use App\Jobs\UpdateQueueWorkerHeartbeatJob;
use App\Services\Operations\OperationalHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            ->expectsOutputToContain('Semua komponen sehat')
            ->assertSuccessful();

        Carbon::setTestNow(now()->addMinutes(6));
        $this->artisan('operations:health')->assertFailed();
    }

    public function test_skip_heartbeat_flag_passes_right_after_a_deploy_before_any_heartbeat_exists(): void
    {
        // No scheduler/worker tick has happened yet (fresh deploy), but the
        // app/database/migrations are healthy - this must not false-fail.
        $this->artisan('operations:health', ['--skip-heartbeat' => true])
            ->expectsOutputToContain('Aplikasi/database/migrasi sehat.')
            ->assertSuccessful();
    }

    public function test_skip_heartbeat_flag_still_fails_on_pending_migrations(): void
    {
        $migration = DB::table('migrations')->orderBy('id')->first();
        DB::table('migrations')->where('id', $migration->id)->delete();

        $this->artisan('operations:health', ['--skip-heartbeat' => true])
            ->expectsOutputToContain('bermasalah')
            ->assertFailed();
    }

    public function test_full_health_check_still_fails_on_stale_heartbeat_even_when_app_is_healthy(): void
    {
        // Regression guard: adding the app/database/migration check must not
        // let a stale heartbeat slip through when --skip-heartbeat isn't used.
        $this->artisan('operations:health')
            ->expectsOutputToContain('tidak aktif/terlambat')
            ->assertFailed();
    }
}
