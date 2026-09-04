<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * PHASE 3 (client-confirmed business rule): Piutang/receivable now counts
 * any non-cancelled, not-yet-paid invoice - draft included - since an
 * invoice can carry a real verified DP and be actively in production while
 * still draft (Invoice::isEditable()). Revenue-recognition scopes
 * (finalized(), still status===sent only) are a separate, untouched
 * concept - see Invoice::scopeReceivable().
 */
class ReceivablePiutangScopeTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_acceptance_scenario_three_invoices_total_390k_across_draft_and_sent(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Piutang Acceptance']);

        // Invoice A: total 300k, verified 150k, outstanding 150k - draft.
        $invoiceA = $this->invoice($customer, 'INV-PIUTANG-A', 300000, Invoice::STATUS_DRAFT);
        $this->verifiedPayment($invoiceA, 150000);

        // Invoice B: total 500k, verified 300k, outstanding 200k - sent.
        $invoiceB = $this->invoice($customer, 'INV-PIUTANG-B', 500000, Invoice::STATUS_SENT);
        $this->verifiedPayment($invoiceB, 300000);

        // Invoice C: total 40k, verified 0, outstanding 40k - draft.
        $this->invoice($customer, 'INV-PIUTANG-C', 40000, Invoice::STATUS_DRAFT);

        $this->assertSame(3, Invoice::query()->receivable()->count());
        $this->assertSame(
            390000.0,
            (float) Invoice::query()->receivable()->get()->sum(
                fn (Invoice $invoice): float => $invoice->remainingAmount(),
            ),
        );

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSee('Rp390.000')
            ->assertSee('3 invoice aktif');
    }

    public function test_draft_invoice_with_partial_payment_counts_as_receivable(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-DRAFT-PARTIAL', 300000, Invoice::STATUS_DRAFT);
        $this->verifiedPayment($invoice, 150000);

        $this->assertTrue(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
    }

    public function test_unpaid_draft_invoice_counts_as_receivable(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-DRAFT-UNPAID', 40000, Invoice::STATUS_DRAFT);

        $this->assertTrue(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
    }

    public function test_cancelled_invoice_never_counts_as_receivable_even_with_unpaid_balance(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-CANCELLED-UNPAID', 100000, Invoice::STATUS_CANCELLED);

        $this->assertFalse(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
    }

    public function test_fully_paid_invoice_does_not_count_as_receivable(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-FULLY-PAID', 100000, Invoice::STATUS_SENT);
        $this->verifiedPayment($invoice, 100000);
        $invoice->forceFill(['payment_status' => Invoice::PAYMENT_PAID])->save();

        $this->assertFalse(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
    }

    public function test_pending_payment_does_not_reduce_outstanding_shown_in_piutang(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-PENDING-PAYMENT', 300000, Invoice::STATUS_SENT);
        $invoice->payments()->create([
            'payment_number' => 'PAY-PENDING-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 150000,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->assertSame(300000.0, $invoice->remainingAmount());
        $this->get(route('payments.receivables.index'))->assertOk()->assertSee('Rp300.000');
    }

    public function test_verified_payment_reduces_outstanding_shown_in_piutang(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-VERIFIED-PAYMENT', 300000, Invoice::STATUS_SENT);
        $this->verifiedPayment($invoice, 150000);

        $this->assertSame(150000.0, $invoice->refresh()->remainingAmount());
    }

    public function test_dashboard_outstanding_total_matches_sum_of_receivable_outstanding(): void
    {
        $customer = $this->customer();
        $invoiceA = $this->invoice($customer, 'INV-DASH-A', 300000, Invoice::STATUS_DRAFT);
        $this->verifiedPayment($invoiceA, 150000);
        $this->invoice($customer, 'INV-DASH-B', 40000, Invoice::STATUS_SENT);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rp190.000')
            ->assertSee('2 invoice');
    }

    public function test_editing_an_invoice_updates_its_outstanding_in_piutang(): void
    {
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 'INV-EDIT-PIUTANG', 300000, Invoice::STATUS_SENT);
        $this->verifiedPayment($invoice, 150000);
        $this->assertSame(150000.0, $invoice->refresh()->remainingAmount());

        $product = Product::query()->create([
            'name' => 'Produk Edit Piutang',
            'sku' => 'EDIT-PIUTANG-01',
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        $this->patchJson(route('api.invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 350000,
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertOk();

        // Total raised from 300k to 350k, verified paid stays 150k ->
        // outstanding must now be 200k, and Piutang must reflect it live.
        $this->assertSame(200000.0, $invoice->refresh()->remainingAmount());
        $this->assertTrue(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
    }

    public function test_a_freshly_created_draft_invoice_is_immediately_readable_in_piutang(): void
    {
        $customer = $this->customer();
        $product = Product::query()->create([
            'name' => 'Produk Baru Piutang',
            'sku' => 'NEW-PIUTANG-01',
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        $response = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 75000,
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated();

        $invoiceId = $response->json('data.id');

        $this->assertTrue(Invoice::query()->receivable()->whereKey($invoiceId)->exists());
        $this->get(route('payments.receivables.index'))->assertOk()->assertSee('Rp75.000');
    }

    private function customer(): Customer
    {
        return Customer::query()->create(['name' => 'PT Piutang Test '.random_int(1000, 9999)]);
    }

    private function invoice(Customer $customer, string $number, int $totalAmount, string $status): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }

    private function verifiedPayment(Invoice $invoice, int $amount): Payment
    {
        $payment = $invoice->payments()->create([
            'payment_number' => 'PAY-PIUTANG-'.random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $invoice->forceFill([
            'payment_status' => $amount >= (float) $invoice->total_amount ? Invoice::PAYMENT_PAID : Invoice::PAYMENT_PARTIAL,
        ])->save();

        return $payment;
    }
}
