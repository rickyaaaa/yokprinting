<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The four Kas & Bank cards answer for the same rows the history below them
 * shows, so they take the same date range. Without one they keep their
 * original meaning: this calendar month.
 */
class CashBankSummaryFollowsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07');
        // ActsAsOwner defines its own setUp(), which this class overrides, so
        // authentication is done here instead.
        $this->actingAs(User::factory()->create());

        BankAccount::query()->firstOrFail()->forceFill(['opening_balance' => 1_000_000])->save();

        $this->transaction('2026-08-10', CashBankTransaction::TYPE_INCOME, 5_000_000);
        $this->transaction('2026-08-18', CashBankTransaction::TYPE_EXPENSE, 300_000);
        $this->transaction('2026-09-07', CashBankTransaction::TYPE_EXPENSE, 600_000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_without_a_range_it_still_reports_the_current_month(): void
    {
        $this->getJson('/api/cash-bank/summary')
            ->assertOk()
            ->assertJsonPath('data.is_filtered', false)
            ->assertJsonPath('data.income_this_month', 0)
            ->assertJsonPath('data.expense_this_month', 600000)
            // Balance stays the live all-time figure: 1jt + 5jt - 300rb - 600rb.
            ->assertJsonPath('data.current_balance', 5100000);
    }

    public function test_a_range_narrows_income_expense_and_net_to_that_period(): void
    {
        $this->getJson('/api/cash-bank/summary?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.is_filtered', true)
            ->assertJsonPath('data.income_this_month', 5000000)
            ->assertJsonPath('data.expense_this_month', 300000)
            ->assertJsonPath('data.net_cash_flow', 4700000)
            // Balance as August closed - the September outflow is not counted yet.
            ->assertJsonPath('data.current_balance', 5700000);
    }

    public function test_one_open_end_is_allowed(): void
    {
        $this->getJson('/api/cash-bank/summary?date_from=2026-09-01')
            ->assertOk()
            ->assertJsonPath('data.is_filtered', true)
            ->assertJsonPath('data.expense_this_month', 600000)
            ->assertJsonPath('data.income_this_month', 0);
    }

    public function test_a_reversed_range_is_rejected_rather_than_silently_returning_zero(): void
    {
        $this->getJson('/api/cash-bank/summary?date_from=2026-09-30&date_to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    private function transaction(string $date, string $type, float $amount): void
    {
        static $sequence = 0;
        $sequence++;

        CashBankTransaction::query()->create([
            'bank_account_id' => BankAccount::query()->firstOrFail()->getKey(),
            'transaction_number' => "KB-SUM-{$sequence}",
            'transaction_date' => $date,
            'type' => $type,
            'category' => $type === CashBankTransaction::TYPE_INCOME ? 'other_income' : 'operational_cost',
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            'amount' => $amount,
            'description' => 'Test ringkasan',
            'source_type' => CashBankTransaction::SOURCE_MANUAL,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }
}
