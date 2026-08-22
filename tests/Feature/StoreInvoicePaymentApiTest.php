<?php

namespace Tests\Feature;

use App\Models\CashBankTransaction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreInvoicePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));
    }

    public function test_guest_cannot_record_an_invoice_payment(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);
        auth()->logout();

        $this->postJson(route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]), [
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 1000000,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_without_payment_permission_cannot_record_a_payment(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->postJson(route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]), [
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 1000000,
        ])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_is_rejected_for_a_cancelled_invoice(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000, status: Invoice::STATUS_CANCELLED);

        $this->postJson(route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]), [
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 1000000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_draft_invoice_can_receive_a_dp_payment(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000, status: Invoice::STATUS_DRAFT);

        $response = $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'reference' => 'BCA-DP-01',
                'amount' => 3000000,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PARTIAL);

        $invoice->refresh();

        // Recording a DP must never silently push the invoice out of draft -
        // sending to the customer stays a separate, explicit action.
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 3000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
    }

    public function test_draft_invoice_can_receive_full_payment_and_is_marked_paid_while_staying_draft(): void
    {
        $invoice = $this->createInvoice(totalAmount: 7500000, status: Invoice::STATUS_DRAFT);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 7500000,
            ],
        )->assertCreated()
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PAID);

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_draft_invoice_payment_records_a_cash_bank_transaction(): void
    {
        $invoice = $this->createInvoice(totalAmount: 4000000, status: Invoice::STATUS_DRAFT);

        $response = $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_CASH,
                'amount' => 4000000,
            ],
        )->assertCreated();

        $paymentId = $response->json('data.id');

        $this->assertDatabaseHas('cash_bank_transactions', [
            'source_type' => CashBankTransaction::SOURCE_PAYMENT,
            'source_id' => $paymentId,
            'type' => CashBankTransaction::TYPE_INCOME,
            'amount' => 4000000,
            'status' => CashBankTransaction::STATUS_POSTED,
        ]);
    }

    public function test_overpayment_is_rejected_for_a_draft_invoice(): void
    {
        $invoice = $this->createInvoice(totalAmount: 5000000, status: Invoice::STATUS_DRAFT);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 5000001,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_second_of_two_rapid_payments_is_bounded_by_the_freshly_locked_remaining_balance(): void
    {
        // Regression guard for the row-locking in RecordInvoicePayment::handle():
        // each call must recompute "remaining" from a freshly locked read of the
        // invoice/payments, not from a value captured before the first payment
        // committed - otherwise two near-simultaneous DPs could jointly overpay.
        $invoice = $this->createInvoice(totalAmount: 6000000, status: Invoice::STATUS_DRAFT);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 4000000,
            ],
        )->assertCreated();

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_MANDIRI,
                'amount' => 3000000,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->refresh()->payment_status);
    }

    public function test_payment_can_be_recorded_for_an_invoice(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);

        $response = $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'reference' => 'BCA-77389',
                'amount' => 4000000,
                'notes' => 'Pembayaran parsial.',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Pembayaran berhasil dicatat.')
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.method', Payment::METHOD_TRANSFER_BCA)
            ->assertJsonPath('data.method_label', 'Transfer BCA')
            ->assertJsonPath('data.reference', 'BCA-77389')
            ->assertJsonPath('data.amount', '4000000.00')
            ->assertJsonPath('data.status', Payment::STATUS_VERIFIED)
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PARTIAL);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'method' => Payment::METHOD_TRANSFER_BCA,
            'reference' => 'BCA-77389',
            'amount' => 4000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $invoice->refresh();

        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        $this->assertNull($invoice->paid_at);
    }

    public function test_full_payment_marks_invoice_as_paid(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 10000000,
            ],
        )->assertCreated()
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PAID);

        $invoice->refresh();

        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_cumulative_verified_payments_mark_invoice_as_paid(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 4000000,
            ],
        )->assertCreated()
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PARTIAL);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-24',
                'method' => Payment::METHOD_TRANSFER_MANDIRI,
                'amount' => 6000000,
            ],
        )->assertCreated()
            ->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PAID);

        $invoice->refresh();

        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_amount_cannot_exceed_remaining_invoice_total(): void
    {
        $invoice = $this->createInvoice(totalAmount: 5000000);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 5000001,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(Invoice::PAYMENT_UNPAID, $invoice->refresh()->payment_status);
    }

    public function test_additional_payment_is_rejected_when_verified_payments_already_cover_invoice(): void
    {
        $invoice = $this->createInvoice(totalAmount: 5000000);
        $invoice->forceFill(['payment_status' => Invoice::PAYMENT_PARTIAL])->save();
        $invoice->payments()->create([
            'payment_number' => 'PAY-20260723-FULL',
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 5000000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->postJson(
            route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]),
            [
                'payment_date' => '2026-07-24',
                'method' => Payment::METHOD_TRANSFER_MANDIRI,
                'amount' => 1000,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath(
                'errors.amount.0',
                'Invoice sudah lunas. Pembayaran tambahan tidak dapat dicatat.',
            );

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unknown_invoice_number_returns_not_found(): void
    {
        $this->postJson('/api/invoices/INV-2026-9999/payments', [
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 1000000,
        ])->assertNotFound();
    }

    private function createInvoice(int $totalAmount, string $status = Invoice::STATUS_SENT): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0084',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }
}
