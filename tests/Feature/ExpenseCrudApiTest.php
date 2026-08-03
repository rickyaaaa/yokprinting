<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseCrudApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_show_update_and_soft_delete_expense_with_private_proof_and_audit_log(): void
    {
        Storage::fake('expense_proofs');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $otherUser = User::factory()->create();
        $this->actingAs($owner);

        $createResponse = $this->post(route('api.expenses.store'), [
            'expense_date' => '2026-08-01',
            'category' => Expense::CATEGORY_EMPLOYEE,
            'subcategory' => Expense::SUBCATEGORY_OVERTIME,
            'amount' => '275000.50',
            'description' => 'Lembur penyelesaian pesanan akhir pekan.',
            'recipient' => 'Tim Produksi Malam',
            'payment_method' => 'Transfer bank',
            'proof_payment' => UploadedFile::fake()->image('bukti-lembur.jpg'),
            'created_by' => $otherUser->id,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.category_label', 'Biaya Karyawan')
            ->assertJsonPath('data.subcategory_label', 'Lemburan')
            ->assertJsonPath('data.amount', 275000.5)
            ->assertJsonPath('data.creator.id', $owner->id);

        $expense = Expense::query()->findOrFail($createResponse->json('data.id'));

        $this->assertSame($owner->id, $expense->created_by);
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);

        $this->getJson(route('api.expenses.show', $expense))
            ->assertOk()
            ->assertJsonPath('data.description', 'Lembur penyelesaian pesanan akhir pekan.')
            ->assertJsonPath('data.proof_original_name', 'bukti-lembur.jpg');

        $oldProofPath = $expense->proof_path;
        $updateResponse = $this->post(route('api.expenses.update', $expense), [
            '_method' => 'PATCH',
            'version' => $expense->version,
            'category' => Expense::CATEGORY_PRODUCTION,
            'subcategory' => '',
            'amount' => '325000.75',
            'description' => 'Biaya finishing pesanan akhir pekan.',
            'created_by' => $otherUser->id,
            'proof_payment' => UploadedFile::fake()->create('bukti-finishing.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.category_label', 'Biaya Produksi')
            ->assertJsonPath('data.subcategory', null)
            ->assertJsonPath('data.amount', 325000.75)
            ->assertJsonPath('data.created_by', $owner->id);

        $expense->refresh();
        Storage::disk('expense_proofs')->assertMissing($oldProofPath);
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);
        $this->assertSame('bukti-finishing.pdf', $expense->proof_original_name);

        $this->deleteJson(route('api.expenses.destroy', $expense))
            ->assertNoContent();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        Storage::disk('expense_proofs')->assertExists($expense->proof_path);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'expense',
            'action' => 'create',
            'subject_type' => Expense::class,
            'subject_id' => $expense->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'expense',
            'action' => 'update',
            'subject_id' => $expense->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'expense',
            'action' => 'delete',
            'risk_level' => ActivityLog::RISK_HIGH,
            'subject_id' => $expense->id,
        ]);
    }

    public function test_expense_list_filters_searches_paginates_and_totals_the_full_filtered_result(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner);

        Expense::factory()->create([
            'created_by' => $owner->id,
            'expense_date' => '2026-07-31',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 100000.25,
            'description' => 'Tinta mesin offset',
            'recipient' => 'Toko Tinta A',
        ]);
        Expense::factory()->create([
            'created_by' => $owner->id,
            'expense_date' => '2026-08-01',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 200000.25,
            'description' => 'Tinta digital',
            'recipient' => 'Toko Tinta B',
        ]);
        Expense::factory()->create([
            'created_by' => $owner->id,
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 300000.25,
            'description' => 'Tinta sablon',
            'recipient' => 'Toko Tinta C',
        ]);
        Expense::factory()->create([
            'created_by' => $owner->id,
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_PREMISES,
            'amount' => 900000,
            'description' => 'Perawatan gedung',
        ]);

        $this->getJson(route('api.expenses.index', [
            'date_from' => '2026-07-31',
            'date_to' => '2026-08-02',
            'category' => Expense::CATEGORY_PRODUCTION,
            'search' => 'Tinta',
            'per_page' => 2,
            'page' => 1,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.total_expense', 600000.75)
            ->assertJsonPath('meta.reference.categories.production', 'Biaya Produksi')
            ->assertJsonPath('meta.reference.employee_subcategories.overtime', 'Lemburan');

        $this->getJson(route('api.expenses.index', [
            'search' => 'Perawatan gedung',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', Expense::CATEGORY_PREMISES)
            ->assertJsonPath('meta.total_expense', 900000);
    }

    public function test_expense_validation_allows_only_requested_categories_and_employee_subcategories(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.expenses.store'), [
            'expense_date' => 'not-a-date',
            'category' => 'transportation',
            'subcategory' => 'fuel',
            'amount' => 0,
            'description' => '',
            'recipient' => '',
            'payment_method' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expense_date',
                'category',
                'subcategory',
                'amount',
                'description',
                'recipient',
                'payment_method',
                'proof_payment',
            ]);

        $this->post(route('api.expenses.store'), [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_EMPLOYEE,
            'amount' => 100000,
            'description' => 'Pembayaran karyawan.',
            'recipient' => 'Karyawan',
            'payment_method' => 'Tunai',
            'proof_payment' => UploadedFile::fake()->image('bukti.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subcategory');

        $this->post(route('api.expenses.store'), [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_PREMISES,
            'amount' => 100000,
            'description' => 'Biaya tempat.',
            'recipient' => 'Pengelola gedung',
            'payment_method' => 'Transfer bank',
            'proof_payment' => UploadedFile::fake()->create('bukti.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proof_payment');

        $this->post(route('api.expenses.store'), [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_SHOPPING,
            'subcategory' => Expense::SUBCATEGORY_BONUS,
            'amount' => 100000,
            'description' => 'Belanja kebutuhan toko.',
            'recipient' => 'Toko ATK',
            'payment_method' => 'Tunai',
            'proof_payment' => UploadedFile::fake()->image('bukti.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subcategory');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_payment_proof_is_downloaded_from_private_storage_and_logged(): void
    {
        Storage::fake('expense_proofs');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $expense = Expense::factory()->create([
            'created_by' => $owner->id,
            'proof_path' => 'expense-proofs/private-proof.pdf',
            'proof_original_name' => 'bukti-private.pdf',
            'proof_mime_type' => 'application/pdf',
        ]);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'private-proof-content');
        $this->actingAs($owner);

        $this->get(route('api.expenses.proof.download', $expense))
            ->assertOk()
            ->assertDownload('bukti-private.pdf');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'expense',
            'action' => 'proof_download_prepared',
            'subject_id' => $expense->id,
        ]);
    }
}
