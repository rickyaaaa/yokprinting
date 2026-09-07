<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\Expense;
use App\Models\User;
use App\Services\Reports\ProfitLossReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Money paid straight out of Kas & Bank is a real business cost and now
 * reaches the profit & loss, which previously only ever read the expenses
 * table.
 *
 * The whole risk here is double counting: recording a cost through the
 * Pengeluaran menu writes an expenses row AND its matching Kas & Bank
 * transaction, so only source_type = manual may be folded in.
 */
class ProfitLossReadsManualCashBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manual_outflow_is_recognised_as_a_business_cost(): void
    {
        // The reported case: Rp300.000 Biaya Operasional typed into Kas & Bank.
        $this->manual('operational_cost', 300000, '2027-03-15');

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(300000.0, $summary['operational_expenses']);
        $this->assertSame(300000.0, $summary['recognized_expenses']);
        $this->assertSame(300000.0, $summary['recorded_expenses']);
        $this->assertSame(1, $summary['expense_count']);
    }

    public function test_an_expense_backed_transaction_is_counted_once_not_twice(): void
    {
        // Saving an Expense also writes a Kas & Bank transaction. If the
        // report summed cash movements wholesale this would read 400.000.
        $expense = $this->expense(Expense::CATEGORY_OPERATIONAL, 200000, '2027-03-15');
        $this->cashBankFor($expense);

        $this->assertDatabaseCount('cash_bank_transactions', 1);

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(200000.0, $summary['operational_expenses']);
        $this->assertSame(200000.0, $summary['recorded_expenses']);
        $this->assertSame(1, $summary['expense_count']);
    }

    public function test_both_sources_add_up_side_by_side(): void
    {
        $expense = $this->expense(Expense::CATEGORY_OPERATIONAL, 200000, '2027-03-15');
        $this->cashBankFor($expense);
        $this->manual('operational_cost', 300000, '2027-03-15');
        $this->manual('bank_fee', 15000, '2027-03-15');

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(500000.0, $summary['operational_expenses']);
        $this->assertSame(15000.0, $summary['bank_fee_expenses']);
        $this->assertSame(515000.0, $summary['recognized_expenses']);
        $this->assertSame(3, $summary['expense_count']);
    }

    public function test_equity_moves_and_tax_are_never_counted_as_costs(): void
    {
        $this->manual('owner_withdrawal', 5000000, '2027-03-15');
        $this->manual('balance_adjustment', 1000, '2027-03-15');
        $this->manual('tax', 250000, '2027-03-15');
        $this->manual('beli galon', 40000, '2027-03-15');

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(0.0, $summary['recorded_expenses']);
        $this->assertSame(0, $summary['expense_count']);
    }

    public function test_cancelled_transactions_and_money_in_are_ignored(): void
    {
        $this->manual('operational_cost', 300000, '2027-03-15')
            ->forceFill(['status' => CashBankTransaction::STATUS_CANCELLED])->save();
        $this->manual('other_income', 900000, '2027-03-15', CashBankTransaction::TYPE_INCOME);

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(0.0, $summary['recorded_expenses']);
    }

    public function test_only_transactions_inside_the_period_are_counted(): void
    {
        $this->manual('operational_cost', 300000, '2027-03-14');
        $this->manual('operational_cost', 700000, '2027-03-15');
        $this->manual('operational_cost', 900000, '2027-03-16');

        $summary = app(ProfitLossReport::class)->build('custom', '2027-03-15', '2027-03-15')['summary'];

        $this->assertSame(700000.0, $summary['operational_expenses']);
    }

    private function manual(
        string $category,
        float $amount,
        string $date,
        string $type = CashBankTransaction::TYPE_EXPENSE,
    ): CashBankTransaction {
        static $sequence = 0;
        $sequence++;

        return CashBankTransaction::query()->create([
            'bank_account_id' => BankAccount::query()->firstOrFail()->getKey(),
            'transaction_number' => "KB-PL-{$sequence}",
            'transaction_date' => $date,
            'type' => $type,
            'category' => $category,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            'amount' => $amount,
            'description' => 'Uang keluar manual',
            'source_type' => CashBankTransaction::SOURCE_MANUAL,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    private function cashBankFor(Expense $expense): CashBankTransaction
    {
        return CashBankTransaction::query()->create([
            'bank_account_id' => BankAccount::query()->firstOrFail()->getKey(),
            'transaction_number' => 'KB-PL-EXP-'.$expense->getKey(),
            'transaction_date' => $expense->expense_date,
            'type' => CashBankTransaction::TYPE_EXPENSE,
            'category' => $expense->category,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            'amount' => $expense->amount,
            'description' => $expense->description,
            'source_type' => CashBankTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->getKey(),
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    private function expense(string $category, float $amount, string $date): Expense
    {
        return Expense::query()->create([
            'expense_date' => $date,
            'category' => $category,
            'amount' => $amount,
            'description' => 'Biaya lewat menu Pengeluaran',
            'recipient' => 'Penerima Test',
            'payment_method' => Expense::METHOD_BANK_TRANSFER,
            'proof_path' => 'expense-proofs/test.pdf',
            'proof_original_name' => 'test.pdf',
            'proof_mime_type' => 'application/pdf',
            'created_by' => User::factory()->create()->getKey(),
        ]);
    }
}
