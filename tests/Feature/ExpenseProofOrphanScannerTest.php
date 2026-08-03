<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseProofOrphanScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scanner_defaults_to_dry_run_and_ignores_referenced_files(): void
    {
        Storage::fake('expense_proofs');
        $expense = Expense::factory()->create(['proof_path' => 'expense-proofs/referenced.pdf']);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'referenced');
        Storage::disk('expense_proofs')->put('expense-proofs/pending-cleanup.pdf', 'pending');
        Storage::disk('expense_proofs')->put('expense-proofs/orphan.pdf', 'orphan');
        ExpenseProofCleanupTask::query()->create([
            'disk' => 'expense_proofs',
            'path' => 'expense-proofs/pending-cleanup.pdf',
            'reason' => 'existing_retry',
        ]);
        Carbon::setTestNow(now()->addDay());

        $this->artisan('expenses:proofs:scan', ['--grace-minutes' => 0])
            ->expectsOutputToContain('expense-proofs/orphan.pdf')
            ->expectsOutputToContain('hanya dilaporkan')
            ->assertSuccessful();

        Storage::disk('expense_proofs')->assertExists('expense-proofs/orphan.pdf');
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);
        Storage::disk('expense_proofs')->assertExists('expense-proofs/pending-cleanup.pdf');
    }

    public function test_scanner_deletes_only_when_explicitly_requested(): void
    {
        Storage::fake('expense_proofs');
        Storage::disk('expense_proofs')->put('expense-proofs/orphan.pdf', 'orphan');
        Carbon::setTestNow(now()->addDay());

        $this->artisan('expenses:proofs:scan', [
            '--delete' => true,
            '--grace-minutes' => 0,
        ])->assertSuccessful();

        Storage::disk('expense_proofs')->assertMissing('expense-proofs/orphan.pdf');
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'proof_cleanup_succeeded',
        ]);
    }
}
