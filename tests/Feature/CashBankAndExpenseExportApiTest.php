<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class CashBankAndExpenseExportApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_cash_bank_export_downloads_csv_filtered_by_period(): void
    {
        $cashBank = app(CashBankService::class);
        $cashBank->recordManualTransaction([
            'transaction_date' => '2026-08-01',
            'type' => 'income',
            'category' => 'owner_capital',
            'amount' => 500_000,
            'description' => 'Modal Agustus',
        ]);
        $cashBank->recordManualTransaction([
            'transaction_date' => '2026-07-01',
            'type' => 'income',
            'category' => 'owner_capital',
            'amount' => 750_000,
            'description' => 'Modal Juli (di luar periode)',
        ]);

        $response = $this->get(route('api.cash-bank.transactions.export', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));
        $content = $response->getContent();
        $this->assertStringContainsString('Modal Agustus', $content);
        $this->assertStringNotContainsString('Modal Juli', $content);
    }

    public function test_expense_export_downloads_csv_filtered_by_period(): void
    {
        Storage::fake('expense_proofs');
        $creator = User::factory()->create();

        Expense::query()->create([
            'expense_date' => '2026-08-10',
            'category' => Expense::CATEGORY_SHOPPING,
            'amount' => 100000,
            'description' => 'Belanja bulan Agustus',
            'recipient' => 'Toko ATK',
            'payment_method' => Expense::METHOD_CASH,
            'proof_path' => 'expense-proofs/test.pdf',
            'proof_original_name' => 'test.pdf',
            'proof_mime_type' => 'application/pdf',
            'created_by' => $creator->id,
        ]);
        Expense::query()->create([
            'expense_date' => '2026-07-10',
            'category' => Expense::CATEGORY_SHOPPING,
            'amount' => 200000,
            'description' => 'Belanja bulan Juli (di luar periode)',
            'recipient' => 'Toko ATK',
            'payment_method' => Expense::METHOD_CASH,
            'proof_path' => 'expense-proofs/test2.pdf',
            'proof_original_name' => 'test2.pdf',
            'proof_mime_type' => 'application/pdf',
            'created_by' => $creator->id,
        ]);

        $response = $this->get(route('api.expenses.export', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));
        $content = $response->getContent();
        $this->assertStringContainsString('Belanja bulan Agustus', $content);
        $this->assertStringNotContainsString('Belanja bulan Juli', $content);
    }

    public function test_guest_cannot_export_cash_bank_or_expenses(): void
    {
        auth()->logout();

        $this->get(route('api.cash-bank.transactions.export'))->assertRedirect(route('login'));
        $this->get(route('api.expenses.export'))->assertRedirect(route('login'));
    }

    public function test_user_without_report_export_permission_is_forbidden(): void
    {
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->get(route('api.cash-bank.transactions.export'))->assertForbidden();
        $this->get(route('api.expenses.export'))->assertForbidden();
    }
}
