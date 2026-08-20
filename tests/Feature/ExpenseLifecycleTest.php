<?php

namespace Tests\Feature;

use App\Jobs\PurgeExpiredExpensesJob;
use App\Jobs\RetryExpenseProofCleanupJob;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use App\Services\Expenses\ExpenseProofCleanup;
use App\Services\Security\ActivityLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExpenseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_soft_deleted_expense_can_be_restored_with_its_proof_and_is_audited(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create(['created_by' => $owner->id]);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'audit-proof');
        $expense->delete();
        $this->actingAs($owner);

        $this->postJson(route('api.expenses.restore', $expense->getKey()))
            ->assertOk()
            ->assertJsonPath('data.id', $expense->getKey())
            ->assertJsonPath('data.version', 2);

        $this->assertNotSoftDeleted('expenses', ['id' => $expense->getKey()]);
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'expense',
            'action' => 'restore',
            'subject_id' => $expense->getKey(),
        ]);
    }

    public function test_restoring_an_expense_reposts_its_cash_bank_transaction(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $account = BankAccount::query()->firstOrFail();
        $account->update(['opening_balance' => 1_000_000]);
        $expense = Expense::factory()->create([
            'created_by' => $owner->id,
            'amount' => 200_000,
            'expense_date' => '2026-08-20',
        ]);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'audit-proof');

        $transaction = app(CashBankService::class)->recordExpense($expense);
        $this->assertNotNull($transaction);
        $this->assertSame(800_000.0, app(CashBankService::class)->calculateBalance($account->fresh()));

        $expense->delete();
        app(CashBankService::class)->cancelExpenseTransaction($expense, $owner->id);
        $this->assertSame(CashBankTransaction::STATUS_CANCELLED, $transaction->fresh()->status);

        $this->actingAs($owner)
            ->postJson(route('api.expenses.restore', $expense->getKey()))
            ->assertOk();

        $this->assertSame(CashBankTransaction::STATUS_POSTED, $transaction->fresh()->status);
        $this->assertSame(800_000.0, app(CashBankService::class)->calculateBalance($account->fresh()));
    }

    public function test_scheduled_purge_uses_configured_retention_boundary_and_deletes_record_and_proof(): void
    {
        Storage::fake('expense_proofs');
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['expenses.proof_retention_days' => 30]);
        $user = User::factory()->create();
        $expired = Expense::factory()->create(['created_by' => $user->id]);
        $retained = Expense::factory()->create(['created_by' => $user->id]);
        Storage::disk('expense_proofs')->put($expired->proof_path, 'expired');
        Storage::disk('expense_proofs')->put($retained->proof_path, 'retained');
        $expired->delete();
        $retained->delete();
        Expense::withTrashed()->whereKey($expired->getKey())->update(['deleted_at' => now()->subDays(30)]);
        Expense::withTrashed()->whereKey($retained->getKey())->update(['deleted_at' => now()->subDays(30)->addSecond()]);

        $purged = app(PurgeExpiredExpensesJob::class)->handle(
            app(ExpenseProofCleanup::class),
            app(ActivityLogger::class),
        );

        $this->assertSame(1, $purged);
        $this->assertNull(Expense::withTrashed()->find($expired->getKey()));
        $this->assertNotNull(Expense::withTrashed()->find($retained->getKey()));
        Storage::disk('expense_proofs')->assertMissing($expired->proof_path);
        Storage::disk('expense_proofs')->assertExists($retained->proof_path);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'purge_queued',
            'subject_id' => $expired->getKey(),
            'risk_level' => ActivityLog::RISK_HIGH,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_succeeded']);
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
    }

    public function test_restore_without_retained_proof_is_rejected_and_failure_is_audited(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create(['created_by' => $owner->id]);
        $expense->delete();
        $this->actingAs($owner);

        $this->postJson(route('api.expenses.restore', $expense->getKey()))
            ->assertStatus(409);

        $this->assertSoftDeleted('expenses', ['id' => $expense->getKey()]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'restore_failed',
            'subject_id' => $expense->getKey(),
            'risk_level' => ActivityLog::RISK_HIGH,
        ]);
    }

    public function test_failed_storage_delete_is_retained_for_retry_and_orphan_is_cleaned_later(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['expenses.cleanup_retry_minutes' => 1]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturnTrue();
        $disk->shouldReceive('delete')->once()->andReturnFalse();
        Storage::set('expense_proofs', $disk);

        $cleanup = app(ExpenseProofCleanup::class);
        $this->assertFalse($cleanup->cleanup('expense-proofs/orphan.pdf', 'rollback'));
        $task = ExpenseProofCleanupTask::query()->firstOrFail();
        $this->assertSame(1, $task->attempts);
        $this->assertNotNull($task->last_error);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_failed']);

        Storage::fake('expense_proofs');
        Storage::disk('expense_proofs')->put($task->path, 'orphan');
        $task->update(['next_attempt_at' => now()]);
        $processed = app(RetryExpenseProofCleanupJob::class)->handle(app(ExpenseProofCleanup::class));

        $this->assertSame(1, $processed);
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        Storage::disk('expense_proofs')->assertMissing($task->path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_succeeded']);
    }

    public function test_purge_database_failure_keeps_record_and_proof_without_creating_outbox_task(): void
    {
        Storage::fake('expense_proofs');
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['expenses.proof_retention_days' => 30]);
        $expense = Expense::factory()->create();
        Storage::disk('expense_proofs')->put($expense->proof_path, 'retained-proof');
        $expense->delete();
        Expense::withTrashed()->whereKey($expense)->update(['deleted_at' => now()->subDays(31)]);
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->once()->andThrow(new RuntimeException('database audit failure'));

        try {
            app(PurgeExpiredExpensesJob::class)->handle(app(ExpenseProofCleanup::class), $logger);
            $this->fail('Expected purge transaction to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database audit failure', $exception->getMessage());
        }

        $this->assertSoftDeleted('expenses', ['id' => $expense->getKey()]);
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
    }

    public function test_purge_commits_outbox_before_storage_failure_and_retry_finishes_cleanup(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['expenses.proof_retention_days' => 30]);
        $realDisk = Storage::fake('expense_proofs');
        $expense = Expense::factory()->create();
        $realDisk->put($expense->proof_path, 'proof');
        $expense->delete();
        Expense::withTrashed()->whereKey($expense)->update(['deleted_at' => now()->subDays(31)]);
        $failingDisk = Mockery::mock($realDisk)->makePartial();
        $failingDisk->shouldReceive('delete')->once()->with($expense->proof_path)->andReturnFalse();
        Storage::set('expense_proofs', $failingDisk);

        $this->assertSame(1, app(PurgeExpiredExpensesJob::class)->handle(
            app(ExpenseProofCleanup::class),
            app(ActivityLogger::class),
        ));

        $this->assertNull(Expense::withTrashed()->find($expense->getKey()));
        $task = ExpenseProofCleanupTask::query()->firstOrFail();
        $this->assertSame('retention_purge', $task->reason);
        $this->assertSame(1, $task->attempts);
        $realDisk->assertExists($expense->proof_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'purge_queued']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_failed']);

        Storage::set('expense_proofs', $realDisk);
        $task->update(['next_attempt_at' => now()]);
        app(RetryExpenseProofCleanupJob::class)->handle(app(ExpenseProofCleanup::class));

        $realDisk->assertMissing($expense->proof_path);
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_succeeded']);
    }

    public function test_cleanup_audit_database_failure_after_file_delete_keeps_outbox_for_retry(): void
    {
        Storage::fake('expense_proofs');
        Storage::disk('expense_proofs')->put('expense-proofs/purge-audit.pdf', 'proof');
        $task = ExpenseProofCleanupTask::query()->create([
            'disk' => 'expense_proofs',
            'path' => 'expense-proofs/purge-audit.pdf',
            'reason' => 'retention_purge',
        ]);
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->twice()->andThrow(new RuntimeException('audit database unavailable'));
        $cleanup = new ExpenseProofCleanup($logger);

        $this->assertFalse($cleanup->attempt($task));
        Storage::disk('expense_proofs')->assertMissing($task->path);
        $this->assertDatabaseHas('expense_proof_cleanup_tasks', [
            'id' => $task->getKey(),
            'attempts' => 1,
        ]);

        $task->refresh()->update(['next_attempt_at' => now()]);
        app(RetryExpenseProofCleanupJob::class)->handle(app(ExpenseProofCleanup::class));

        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'proof_cleanup_succeeded']);
    }

    public function test_cleanup_task_cannot_be_claimed_by_a_second_worker_until_lease_expires(): void
    {
        Storage::fake('expense_proofs');
        Carbon::setTestNow('2026-08-02 10:00:00');
        config(['expenses.cleanup_claim_timeout_minutes' => 30]);
        $task = ExpenseProofCleanupTask::query()->create([
            'disk' => 'expense_proofs',
            'path' => 'expense-proofs/claimed.pdf',
            'reason' => 'proof_replaced',
            'next_attempt_at' => now(),
        ]);
        Storage::disk('expense_proofs')->put($task->path, 'proof');
        $cleanup = app(ExpenseProofCleanup::class);

        $firstClaim = $cleanup->claim($task->getKey());

        $this->assertNotNull($firstClaim);
        $this->assertSame(ExpenseProofCleanupTask::STATUS_PROCESSING, $firstClaim->status);
        $this->assertNull($cleanup->claim($task->getKey()));
        $this->assertFalse($cleanup->attempt($task));
        Storage::disk('expense_proofs')->assertExists($task->path);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'proof_cleanup_succeeded']);

        Carbon::setTestNow(now()->addMinutes(31));
        $recoveredClaim = $cleanup->claim($task->getKey());
        $this->assertNotNull($recoveredClaim);
        $this->assertNotSame($firstClaim->claim_token, $recoveredClaim->claim_token);
    }

    public function test_cleanup_outbox_migration_rollback_fails_fast_while_tasks_are_pending(): void
    {
        ExpenseProofCleanupTask::query()->create([
            'disk' => 'expense_proofs',
            'path' => 'expense-proofs/pending.pdf',
            'reason' => 'retention_purge',
        ]);
        $migration = require database_path('migrations/2026_08_02_020000_add_expense_version_and_proof_cleanup_tasks.php');

        try {
            $migration->down();
            $this->fail('Rollback should fail while cleanup tasks remain.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('masih memiliki task pending', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('expense_proof_cleanup_tasks'));
        $this->assertTrue(Schema::hasColumn('expenses', 'version'));
    }

    public function test_cleanup_outbox_migration_rolls_back_when_no_tasks_remain(): void
    {
        $migration = require database_path('migrations/2026_08_02_020000_add_expense_version_and_proof_cleanup_tasks.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('expense_proof_cleanup_tasks'));
        $this->assertFalse(Schema::hasColumn('expenses', 'version'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('expense_proof_cleanup_tasks'));
        $this->assertTrue(Schema::hasColumn('expenses', 'version'));
    }
}
