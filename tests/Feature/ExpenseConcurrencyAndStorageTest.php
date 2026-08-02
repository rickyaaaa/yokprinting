<?php

namespace Tests\Feature;

use App\Jobs\RetryExpenseProofCleanupJob;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Models\User;
use App\Services\Expenses\ExpenseProofCleanup;
use App\Services\Security\ActivityLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExpenseConcurrencyAndStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_concurrent_update_is_rejected_and_does_not_leave_an_uploaded_file(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create([
            'created_by' => $owner->id,
            'proof_path' => 'expense-proofs/original.pdf',
        ]);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'original');
        $this->actingAs($owner);

        $first = $this->post(route('api.expenses.update', $expense), [
            '_method' => 'PATCH',
            'version' => 1,
            'description' => 'Perubahan pertama.',
            'proof_payment' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $firstPath = Expense::query()->findOrFail($expense->getKey())->proof_path;

        $this->post(route('api.expenses.update', $expense), [
            '_method' => 'PATCH',
            'version' => 1,
            'description' => 'Perubahan stale.',
            'proof_payment' => UploadedFile::fake()->create('stale.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('current_version', 2);

        $expense->refresh();
        $this->assertSame('Perubahan pertama.', $expense->description);
        $this->assertSame(2, $expense->version);
        $this->assertSame('first.pdf', $expense->proof_original_name);
        $this->assertSame(2, $first->json('data.version'));
        Storage::disk('expense_proofs')->assertExists($firstPath);
        $this->assertSame([$firstPath], Storage::disk('expense_proofs')->allFiles('expense-proofs'));
    }

    public function test_database_failure_rolls_back_expense_and_cleans_new_proof(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner);
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->andThrow(new RuntimeException('audit database failure'));
        $this->app->instance(ActivityLogger::class, $logger);

        $this->post(route('api.expenses.store'), $this->validPayload(), ['Accept' => 'application/json'])
            ->assertServerError();

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        $this->assertSame([], Storage::disk('expense_proofs')->allFiles('expense-proofs'));
    }

    public function test_failed_old_proof_delete_keeps_successful_update_consistent_and_retries_cleanup(): void
    {
        Storage::fake('expense_proofs');
        $realDisk = Storage::disk('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create([
            'created_by' => $owner->id,
            'proof_path' => 'expense-proofs/old.pdf',
        ]);
        $realDisk->put($expense->proof_path, 'old-proof');
        $oldPath = $expense->proof_path;
        $failingDisk = Mockery::mock($realDisk)->makePartial();
        $failingDisk->shouldReceive('delete')->once()->with($oldPath)->andReturnFalse();
        Storage::set('expense_proofs', $failingDisk);
        $this->actingAs($owner);

        $this->post(route('api.expenses.update', $expense), [
            '_method' => 'PATCH',
            'version' => 1,
            'description' => 'Update tetap committed.',
            'proof_payment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $expense->refresh();
        $this->assertSame('Update tetap committed.', $expense->description);
        $this->assertSame('new.pdf', $expense->proof_original_name);
        $this->assertNotSame($oldPath, $expense->proof_path);
        $task = ExpenseProofCleanupTask::query()->firstOrFail();
        $this->assertSame($oldPath, $task->path);
        $this->assertSame(1, $task->attempts);

        Storage::set('expense_proofs', $realDisk);
        $task->update(['next_attempt_at' => now()]);
        app(RetryExpenseProofCleanupJob::class)->handle(app(ExpenseProofCleanup::class));

        $this->assertDatabaseCount('expense_proof_cleanup_tasks', 0);
        $realDisk->assertMissing($oldPath);
        $realDisk->assertExists($expense->proof_path);
    }

    public function test_download_audit_records_request_and_storage_failure_without_false_success(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create(['created_by' => $owner->id]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('readStream')->once()->andThrow(new RuntimeException('storage unavailable'));
        Storage::set('expense_proofs', $disk);
        $this->actingAs($owner);

        $this->get(route('api.expenses.proof.download', $expense))->assertStatus(503);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'proof_download_requested',
            'subject_id' => $expense->getKey(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'proof_download_failed',
            'subject_id' => $expense->getKey(),
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'proof_download_prepared',
            'subject_id' => $expense->getKey(),
        ]);
    }

    public function test_exactly_five_megabytes_is_accepted_and_larger_file_is_rejected(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $accepted = $this->validPayload();
        $accepted['proof_payment'] = UploadedFile::fake()->create('proof.pdf', 5120, 'application/pdf');
        $this->post(route('api.expenses.store'), $accepted, ['Accept' => 'application/json'])
            ->assertCreated();

        $rejected = $this->validPayload();
        $rejected['proof_payment'] = UploadedFile::fake()->create('proof.pdf', 5121, 'application/pdf');
        $this->post(route('api.expenses.store'), $rejected, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proof_payment');
    }

    public function test_control_characters_are_removed_from_stored_and_downloaded_filename(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner);
        $payload = $this->validPayload();
        $payload['proof_payment'] = UploadedFile::fake()->create("proof\r\nX-Evil.pdf", 10, 'application/pdf');

        $response = $this->post(route('api.expenses.store'), $payload, ['Accept' => 'application/json'])
            ->assertCreated();
        $expense = Expense::query()->findOrFail($response->json('data.id'));

        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $expense->proof_original_name);
        $download = $this->get(route('api.expenses.proof.download', $expense))->assertOk();
        $this->assertStringNotContainsString("\r", (string) $download->headers->get('content-disposition'));
        $this->assertStringNotContainsString("\n", (string) $download->headers->get('content-disposition'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'proof_download_prepared',
            'risk_level' => ActivityLog::RISK_LOW,
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_SHOPPING,
            'amount' => 50000,
            'description' => 'Belanja kebutuhan.',
            'recipient' => 'Toko',
            'payment_method' => 'Tunai',
            'proof_payment' => UploadedFile::fake()->image('proof.jpg'),
        ];
    }
}
