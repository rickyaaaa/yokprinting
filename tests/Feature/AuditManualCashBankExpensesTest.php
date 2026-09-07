<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reconciliation audit is read-only: it tells us which manual Kas & Bank
 * outflows should have been expenses, without touching anything.
 */
class AuditManualCashBankExpensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_separates_real_expenses_from_equity_moves_and_free_typed_categories(): void
    {
        $this->manual('operational_cost', 300000, 'Biaya operasional Agustus');
        $this->manual('bank_fee', 15000, 'Admin bank');
        $this->manual('owner_withdrawal', 5000000, 'Ambil modal');
        $this->manual('balance_adjustment', 1000, 'Koreksi');
        $this->manual('tax', 250000, 'PPN');
        $this->manual('beli galon', 40000, 'Diketik bebas');

        $this->artisan('cashbank:audit-expense-candidates')
            ->expectsOutputToContain('6 posted manual outflow(s)')
            ->expectsOutputToContain('WOULD BECOME AN EXPENSE')
            ->expectsOutputToContain('NEEDS A DECISION')
            ->expectsOutputToContain('CORRECTLY NOT AN EXPENSE')
            ->expectsOutputToContain('UNRECOGNISED CATEGORY')
            // Only operational_cost + bank_fee are backfillable: 315.000.
            ->expectsOutputToContain('Rp315.000')
            ->assertSuccessful();
    }

    public function test_it_never_writes_anything(): void
    {
        $transaction = $this->manual('operational_cost', 300000, 'Biaya operasional');

        $this->artisan('cashbank:audit-expense-candidates')->assertSuccessful();

        $after = $transaction->fresh();
        $this->assertSame(CashBankTransaction::SOURCE_MANUAL, $after->source_type);
        $this->assertNull($after->source_id);
        $this->assertSame('operational_cost', $after->category);
        $this->assertSame(CashBankTransaction::STATUS_POSTED, $after->status);
        $this->assertSame(300000.0, (float) $after->amount);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('cash_bank_transactions', 1);
    }

    public function test_it_ignores_cancelled_rows_and_money_coming_in(): void
    {
        $this->manual('operational_cost', 300000, 'Dibatalkan')
            ->forceFill(['status' => CashBankTransaction::STATUS_CANCELLED])->save();
        $this->manual('other_income', 900000, 'Uang masuk', CashBankTransaction::TYPE_INCOME);

        $this->artisan('cashbank:audit-expense-candidates')
            ->expectsOutputToContain('nothing to reconcile')
            ->assertSuccessful();
    }

    private function manual(
        string $category,
        float $amount,
        string $description,
        string $type = CashBankTransaction::TYPE_EXPENSE,
    ): CashBankTransaction {
        static $sequence = 0;
        $sequence++;

        return CashBankTransaction::query()->create([
            'bank_account_id' => BankAccount::query()->firstOrFail()->getKey(),
            'transaction_number' => "KB-TEST-{$sequence}",
            'transaction_date' => '2026-08-18',
            'type' => $type,
            'category' => $category,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            'amount' => $amount,
            'description' => $description,
            'source_type' => CashBankTransaction::SOURCE_MANUAL,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }
}
