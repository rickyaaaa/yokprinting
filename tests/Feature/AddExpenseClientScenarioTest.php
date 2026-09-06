<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reproduction of the reported "Tambah Pengeluaran stuck at Menyimpan…" case,
 * using the client's exact input.
 */
class AddExpenseClientScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    /** @return array<string, mixed> */
    private function payload(UploadedFile $proof): array
    {
        return [
            'expense_date' => '2026-09-05',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 3000000,
            'recipient' => 'Imam',
            'payment_method' => Expense::METHOD_BANK_TRANSFER,
            'description' => 'biaya produksi',
            'proof_payment' => $proof,
        ];
    }

    public function test_the_exact_reported_input_saves_and_creates_one_cash_bank_transaction(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAs($this->owner());

        $this->postJson('/api/expenses', $this->payload(
            UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
        ))->assertCreated();

        $this->assertDatabaseCount('expenses', 1);
        $expense = Expense::query()->firstOrFail();
        $this->assertSame(3000000.0, (float) $expense->amount);

        // Exactly one, never duplicated.
        $this->assertSame(1, CashBankTransaction::query()
            ->where('source_type', CashBankTransaction::SOURCE_EXPENSE)
            ->where('source_id', $expense->getKey())
            ->count());

        // And it shows up on the list the client said stayed empty.
        $this->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonPath('data.0.recipient', 'Imam');
    }

    public function test_the_form_shape_the_browser_actually_sends_is_accepted(): void
    {
        // The Alpine form posts every field it holds, so a non-employee
        // expense still carries subcategory as an empty string. That has to
        // survive the prohibitedIf rule, which is only satisfied by a field
        // that is missing *or empty*.
        Storage::fake('expense_proofs');
        $this->actingAs($this->owner());

        $this->postJson('/api/expenses', [
            ...$this->payload(UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf')),
            'subcategory' => '',
        ])->assertCreated();

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_jpg_and_png_proofs_are_accepted_too(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAs($this->owner());

        foreach ([
            UploadedFile::fake()->image('bukti.jpg'),
            UploadedFile::fake()->image('bukti.png'),
        ] as $proof) {
            $this->postJson('/api/expenses', $this->payload($proof))->assertCreated();
        }

        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_a_deactivated_bank_account_fails_loudly_instead_of_saving_a_half_expense(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAs($this->owner());
        BankAccount::query()->update(['is_active' => false]);

        $response = $this->postJson('/api/expenses', $this->payload(
            UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
        ));

        // Whatever the status, the write must not half-succeed.
        $this->assertNotEquals(201, $response->status());
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('cash_bank_transactions', 0);
    }
}
