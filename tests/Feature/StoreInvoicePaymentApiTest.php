<?php

namespace Tests\Feature;

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

    public function test_payment_is_rejected_until_invoice_is_sent(): void
    {
        $invoice = $this->createInvoice(totalAmount: 10000000);

        foreach ([Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED] as $status) {
            $invoice->forceFill(['status' => $status])->save();

            $this->postJson(route('api.invoices.payments.store', ['invoice' => $invoice->invoice_number]), [
                'payment_date' => '2026-07-23',
                'method' => Payment::METHOD_TRANSFER_BCA,
                'amount' => 1000000,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('invoice');
        }

        $this->assertDatabaseCount('payments', 0);
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

    private function createInvoice(int $totalAmount): Invoice
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
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }
}
