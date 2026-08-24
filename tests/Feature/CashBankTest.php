<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CashBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balance_is_returned_as_current_balance_without_transactions(): void
    {
        $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 5_000_000]);

        $this->getJson(route('api.cash-bank.summary'))
            ->assertOk()
            ->assertJsonPath('data.opening_balance', 5_000_000)
            ->assertJsonPath('data.current_balance', 5_000_000)
            ->assertJsonPath('data.account_name', 'Rekening Utama');
    }

    public function test_verified_bank_payment_creates_income_transaction(): void
    {
        $this->actingAsOwner();
        $invoice = $this->createInvoice();

        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-08-01',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 2_000_000,
        ])->assertCreated();

        $payment = Payment::query()->firstOrFail();
        $this->assertDatabaseHas('cash_bank_transactions', [
            'type' => CashBankTransaction::TYPE_INCOME,
            'category' => 'invoice_payment',
            'amount' => 2_000_000,
            'source_type' => CashBankTransaction::SOURCE_PAYMENT,
            'source_id' => $payment->id,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_pending_payment_does_not_create_income_transaction(): void
    {
        $payment = $this->createPayment(Payment::STATUS_PENDING, Payment::METHOD_TRANSFER_BCA);

        $this->assertNull(app(CashBankService::class)->recordPayment($payment));
        $this->assertDatabaseCount('cash_bank_transactions', 0);
    }

    public function test_verified_cash_payment_creates_income_transaction_tagged_as_cash(): void
    {
        $this->actingAsOwner();
        $invoice = $this->createInvoice();

        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-08-01',
            'method' => Payment::METHOD_CASH,
            'amount' => 500_000,
        ])->assertCreated();

        $payment = Payment::query()->firstOrFail();
        $this->assertDatabaseHas('cash_bank_transactions', [
            'type' => CashBankTransaction::TYPE_INCOME,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_CASH,
            'amount' => 500_000,
            'source_type' => CashBankTransaction::SOURCE_PAYMENT,
            'source_id' => $payment->id,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_same_payment_cannot_create_ledger_twice(): void
    {
        $payment = $this->createPayment(Payment::STATUS_VERIFIED, Payment::METHOD_TRANSFER_BCA);
        $service = app(CashBankService::class);

        $first = $service->recordPayment($payment);
        $second = $service->recordPayment($payment->refresh());

        $this->assertSame($first?->id, $second?->id);
        $this->assertDatabaseCount('cash_bank_transactions', 1);
    }

    public function test_bank_expense_creates_expense_ledger_in_controller_flow(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAsOwner();

        $response = $this->post(route('api.expenses.store'), $this->expensePayload(), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertDatabaseHas('cash_bank_transactions', [
            'type' => CashBankTransaction::TYPE_EXPENSE,
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 750_000,
            'source_type' => CashBankTransaction::SOURCE_EXPENSE,
            'source_id' => $response->json('data.id'),
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_editing_bank_expense_updates_the_same_ledger(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAsOwner();
        $expense = $this->createExpenseThroughApi();
        $transaction = $expense->cashBankTransaction()->firstOrFail();

        $this->patchJson(route('api.expenses.update', $expense), [
            'version' => $expense->version,
            'expense_date' => '2026-08-05',
            'amount' => 900_000,
            'description' => 'Biaya produksi yang sudah dikoreksi.',
        ])->assertOk();

        $this->assertDatabaseCount('cash_bank_transactions', 1);
        $this->assertDatabaseHas('cash_bank_transactions', [
            'id' => $transaction->id,
            'transaction_date' => '2026-08-05 00:00:00',
            'amount' => 900_000,
            'description' => 'Biaya produksi yang sudah dikoreksi.',
        ]);
    }

    public function test_deleting_expense_cancels_its_ledger_without_deleting_it(): void
    {
        Storage::fake('expense_proofs');
        $owner = $this->actingAsOwner();
        $expense = $this->createExpenseThroughApi();
        $transaction = $expense->cashBankTransaction()->firstOrFail();

        $this->deleteJson(route('api.expenses.destroy', $expense))->assertNoContent();

        $this->assertDatabaseHas('cash_bank_transactions', [
            'id' => $transaction->id,
            'status' => CashBankTransaction::STATUS_CANCELLED,
            'cancelled_by' => $owner->id,
        ]);
        $this->assertNotNull($transaction->refresh()->cancelled_at);
    }

    public function test_cancelled_transaction_is_excluded_from_balance(): void
    {
        $owner = $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 1_000_000]);
        $transaction = app(CashBankService::class)->recordManualTransaction([
            'transaction_date' => '2026-08-01', 'type' => 'expense', 'category' => 'bank_fee',
            'amount' => 200_000, 'description' => 'Biaya admin bank.',
        ], $owner->id);

        app(CashBankService::class)->cancelTransaction($transaction, $owner->id);

        $this->getJson(route('api.cash-bank.summary'))
            ->assertOk()
            ->assertJsonPath('data.current_balance', 1_000_000);
    }

    public function test_manual_income_adds_to_balance(): void
    {
        $this->actingAsOwner();

        $this->postJson(route('api.cash-bank.transactions.store'), [
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'owner_capital',
            'amount' => 1_250_000, 'description' => 'Tambahan modal owner.',
        ])->assertCreated()->assertJsonPath('data.source_type', CashBankTransaction::SOURCE_MANUAL);

        $this->getJson(route('api.cash-bank.summary'))->assertJsonPath('data.current_balance', 1_250_000);
    }

    public function test_manual_transaction_defaults_to_transfer_when_payment_method_omitted(): void
    {
        $this->actingAsOwner();

        $this->postJson(route('api.cash-bank.transactions.store'), [
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'owner_capital',
            'amount' => 500_000, 'description' => 'Modal via transfer.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', CashBankTransaction::PAYMENT_METHOD_TRANSFER)
            ->assertJsonPath('data.payment_method_label', 'Transfer');
    }

    public function test_manual_transaction_can_be_recorded_as_cash(): void
    {
        $this->actingAsOwner();

        $this->postJson(route('api.cash-bank.transactions.store'), [
            'transaction_date' => '2026-08-01', 'type' => 'expense', 'category' => 'operational_cost',
            'payment_method' => 'cash', 'amount' => 150_000, 'description' => 'Beli perlengkapan kecil.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', CashBankTransaction::PAYMENT_METHOD_CASH)
            ->assertJsonPath('data.payment_method_label', 'Tunai');

        $this->getJson(route('api.cash-bank.transactions.index', ['payment_method' => 'cash']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('api.cash-bank.transactions.index', ['payment_method' => 'transfer']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manual_expense_reduces_balance(): void
    {
        $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 2_000_000]);

        $this->postJson(route('api.cash-bank.transactions.store'), [
            'transaction_date' => '2026-08-01', 'type' => 'expense', 'category' => 'operational_cost',
            'amount' => 350_000, 'description' => 'Biaya operasional manual.',
        ])->assertCreated();

        $this->getJson(route('api.cash-bank.summary'))->assertJsonPath('data.current_balance', 1_650_000);
    }

    public function test_transaction_history_returns_correct_running_balance_in_chronological_order(): void
    {
        $owner = $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 1_000_000]);
        $service = app(CashBankService::class);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'other_income',
            'amount' => 500_000, 'description' => 'Pendapatan lain.',
        ], $owner->id);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-02', 'type' => 'expense', 'category' => 'bank_fee',
            'amount' => 200_000, 'description' => 'Biaya bank.',
        ], $owner->id);

        // Chronological reading order (sort=oldest) - the default display
        // order is newest-first (see the dedicated sort-order test below).
        $this->getJson(route('api.cash-bank.transactions.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertJsonPath('data.0.transaction_date', '2026-08-01')
            ->assertJsonPath('data.0.running_balance', 1_500_000)
            ->assertJsonPath('data.1.transaction_date', '2026-08-02')
            ->assertJsonPath('data.1.running_balance', 1_300_000);
    }

    public function test_transaction_history_defaults_to_newest_first(): void
    {
        // Client requirement: recent activity (a payment/expense just
        // recorded) must land on page 1 by default, not be buried behind
        // older rows - see CashBankController::index().
        $owner = $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 1_000_000]);
        $service = app(CashBankService::class);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'other_income',
            'amount' => 500_000, 'description' => 'Pendapatan lain.',
        ], $owner->id);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-02', 'type' => 'expense', 'category' => 'bank_fee',
            'amount' => 200_000, 'description' => 'Biaya bank.',
        ], $owner->id);

        $this->getJson(route('api.cash-bank.transactions.index'))
            ->assertOk()
            ->assertJsonPath('meta.filters.sort', 'latest')
            ->assertJsonPath('data.0.transaction_date', '2026-08-02')
            ->assertJsonPath('data.0.running_balance', 1_300_000, 'the balance shown must still be this row\'s TRUE running balance, not affected by display order')
            ->assertJsonPath('data.1.transaction_date', '2026-08-01')
            ->assertJsonPath('data.1.running_balance', 1_500_000);
    }

    public function test_period_filter_calculates_beginning_balance_from_prior_transactions(): void
    {
        $owner = $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 1_000_000]);
        $service = app(CashBankService::class);
        $service->recordManualTransaction([
            'transaction_date' => '2026-07-31', 'type' => 'income', 'category' => 'other_income',
            'amount' => 500_000, 'description' => 'Sebelum periode.',
        ], $owner->id);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-02', 'type' => 'expense', 'category' => 'bank_fee',
            'amount' => 200_000, 'description' => 'Dalam periode.',
        ], $owner->id);

        $this->getJson(route('api.cash-bank.transactions.index', ['date_from' => '2026-08-01', 'sort' => 'oldest']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.beginning_balance', 1_500_000)
            ->assertJsonPath('data.0.running_balance', 1_300_000);
    }

    public function test_running_balance_includes_transactions_hidden_by_type_filter(): void
    {
        $owner = $this->actingAsOwner();
        BankAccount::query()->firstOrFail()->update(['opening_balance' => 1_000_000]);
        $service = app(CashBankService::class);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'other_income',
            'amount' => 500_000, 'description' => 'Pemasukan pertama.',
        ], $owner->id);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-02', 'type' => 'expense', 'category' => 'bank_fee',
            'amount' => 200_000, 'description' => 'Pengeluaran tersembunyi oleh filter.',
        ], $owner->id);
        $service->recordManualTransaction([
            'transaction_date' => '2026-08-03', 'type' => 'income', 'category' => 'other_income',
            'amount' => 100_000, 'description' => 'Pemasukan kedua.',
        ], $owner->id);

        $this->getJson(route('api.cash-bank.transactions.index', ['type' => CashBankTransaction::TYPE_INCOME, 'sort' => 'oldest']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.running_balance', 1_500_000)
            ->assertJsonPath('data.1.running_balance', 1_400_000);
    }

    public function test_transaction_history_uses_a_constant_number_of_ledger_queries(): void
    {
        $owner = $this->actingAsOwner();
        $account = BankAccount::query()->firstOrFail();
        $now = now();
        $rows = [];

        foreach (range(1, 50) as $sequence) {
            $rows[] = [
                'bank_account_id' => $account->id,
                'transaction_number' => 'KB-IN-202608-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'transaction_date' => '2026-08-01',
                'type' => CashBankTransaction::TYPE_INCOME,
                'category' => 'other_income',
                'amount' => 10_000,
                'description' => "Transaksi performa {$sequence}.",
                'source_type' => CashBankTransaction::SOURCE_MANUAL,
                'status' => CashBankTransaction::STATUS_POSTED,
                'created_by' => $owner->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        CashBankTransaction::query()->insert($rows);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(route('api.cash-bank.transactions.index', ['per_page' => 50, 'sort' => 'oldest']))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.49.running_balance', 500_000);

        $ledgerQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'cash_bank_transactions'));

        $this->assertLessThanOrEqual(4, $ledgerQueries->count());
    }

    public function test_user_without_permission_cannot_create_manual_transaction(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role' => $role->code]);
        $this->actingAs($user);

        $this->postJson(route('api.cash-bank.transactions.store'), [
            'transaction_date' => '2026-08-01', 'type' => 'income', 'category' => 'other_income',
            'amount' => 100_000, 'description' => 'Tidak diizinkan.',
        ])->assertForbidden();

        $this->assertDatabaseCount('cash_bank_transactions', 0);
    }

    public function test_automatic_transaction_cannot_be_edited_through_manual_api(): void
    {
        $this->actingAsOwner();
        $payment = $this->createPayment(Payment::STATUS_VERIFIED, Payment::METHOD_TRANSFER_BCA);
        $transaction = app(CashBankService::class)->recordPayment($payment);

        $this->patchJson(route('api.cash-bank.transactions.update', $transaction), [
            'amount' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction');

        $this->assertSame('2000000.00', $transaction?->refresh()->amount);
    }

    public function test_cash_expense_creates_ledger_immediately_and_flips_to_bank_on_the_same_row(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAsOwner();
        $payload = $this->expensePayload();
        $payload['payment_method'] = Expense::METHOD_CASH;
        $response = $this->post(route('api.expenses.store'), $payload, ['Accept' => 'application/json'])
            ->assertCreated();
        $expense = Expense::query()->findOrFail($response->json('data.id'));
        $transaction = $expense->cashBankTransaction()->firstOrFail();
        $this->assertSame(CashBankTransaction::PAYMENT_METHOD_CASH, $transaction->payment_method);

        $this->patchJson(route('api.expenses.update', $expense), [
            'version' => $expense->version,
            'payment_method' => Expense::METHOD_BANK_TRANSFER,
        ])->assertOk();

        $this->assertDatabaseCount('cash_bank_transactions', 1);
        $this->assertDatabaseHas('cash_bank_transactions', [
            'id' => $transaction->id,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_bank_expense_ledger_flips_to_cash_on_the_same_row_when_changed(): void
    {
        Storage::fake('expense_proofs');
        $this->actingAsOwner();
        $expense = $this->createExpenseThroughApi();
        $transaction = $expense->cashBankTransaction()->firstOrFail();
        $this->assertSame(CashBankTransaction::PAYMENT_METHOD_TRANSFER, $transaction->payment_method);

        $this->patchJson(route('api.expenses.update', $expense), [
            'version' => $expense->version,
            'payment_method' => Expense::METHOD_CASH,
        ])->assertOk();

        $this->assertDatabaseCount('cash_bank_transactions', 1);
        $this->assertDatabaseHas('cash_bank_transactions', [
            'id' => $transaction->id,
            'payment_method' => CashBankTransaction::PAYMENT_METHOD_CASH,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_payment_reversal_cancels_ledger_without_deleting_it(): void
    {
        $payment = $this->createPayment(Payment::STATUS_VERIFIED, Payment::METHOD_TRANSFER_BCA);
        $service = app(CashBankService::class);
        $transaction = $service->recordPayment($payment);

        $service->cancelPaymentTransaction($payment);

        $this->assertDatabaseHas('cash_bank_transactions', [
            'id' => $transaction?->id,
            'status' => CashBankTransaction::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseCount('cash_bank_transactions', 1);
    }

    public function test_finance_role_seeder_grants_cash_bank_crud_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $finance = Role::query()->where('code', Role::CODE_FINANCE_ADMIN)->firstOrFail();

        $this->assertSame([
            'cash_bank.create',
            'cash_bank.delete',
            'cash_bank.update',
            'cash_bank.view',
        ], $finance->permissions()->where('module', Permission::MODULE_CASH_BANK)->orderBy('code')->pluck('code')->all());
    }

    private function actingAsOwner(): User
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner);

        return $owner;
    }

    private function createInvoice(): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-KB-001', 'name' => 'Pelanggan Kas Bank', 'email' => 'finance@example.test',
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-KB-2026-0001',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => 10_000_000,
        ]);
    }

    private function createPayment(string $status, string $method): Payment
    {
        $invoice = $this->createInvoice();

        return $invoice->payments()->create([
            'payment_number' => 'PAY-KB-202608-0001',
            'payment_date' => '2026-08-01',
            'method' => $method,
            'currency' => 'IDR',
            'amount' => 2_000_000,
            'status' => $status,
            'verified_at' => $status === Payment::STATUS_VERIFIED ? now() : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function expensePayload(): array
    {
        return [
            'expense_date' => '2026-08-03',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 750_000,
            'description' => 'Biaya bahan produksi.',
            'recipient' => 'Supplier Utama',
            'payment_method' => Expense::METHOD_BANK_TRANSFER,
            'proof_payment' => UploadedFile::fake()->image('bukti.jpg'),
        ];
    }

    private function createExpenseThroughApi(): Expense
    {
        $response = $this->post(route('api.expenses.store'), $this->expensePayload(), [
            'Accept' => 'application/json',
        ])->assertCreated();

        return Expense::query()->findOrFail($response->json('data.id'));
    }
}
