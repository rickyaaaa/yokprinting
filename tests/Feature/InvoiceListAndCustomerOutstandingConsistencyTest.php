<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * PHASE 4: two places computed an "outstanding/receivable" figure with
 * their OWN inline status=sent filter instead of reusing
 * Invoice::scopeReceivable() - a second source of truth that silently
 * drifted out of sync the moment Phase 3 widened the scope's real
 * definition. Both now mirror the same rule (status != cancelled &&
 * payment_status != paid) by hand, since both operate on an
 * already-loaded Collection rather than a query builder.
 */
class InvoiceListAndCustomerOutstandingConsistencyTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_list_menunggu_bayar_card_includes_draft_invoices(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Invoice List Outstanding']);

        $draft = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LIST-DRAFT',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 40000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LIST-SENT',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 60000,
        ]);
        $cancelled = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LIST-CANCELLED',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_CANCELLED,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 999999,
        ]);

        // 40k (draft) + 60k (sent) = 100k - the cancelled invoice's 999999
        // must never be counted, regardless of its unpaid balance.
        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Rp100.000');

        $this->assertNotNull($draft);
        $this->assertNotNull($cancelled);
    }

    public function test_customer_page_outstanding_includes_draft_invoices_but_total_sales_stays_sent_only(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-OUT-01', 'name' => 'PT Customer Outstanding']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CUST-DRAFT',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 40000,
        ]);
        $sent = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CUST-SENT',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'total_amount' => 100000,
        ]);
        $sent->payments()->create([
            'payment_number' => 'PAY-CUST-OUT-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 60000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        // Outstanding: 40k (draft, unpaid) + 40k (sent, 100k - 60k paid) = 80k.
        // Total transaksi ("invoice final"/totalSales) stays sent-only: 100k,
        // 1 invoice - the draft must not inflate revenue-recognition figures.
        $this->get(route('customers.show', $customer->code))
            ->assertOk()
            ->assertSee('Rp80.000')
            ->assertSee('Rp100.000')
            ->assertSee('1 invoice final');
    }
}
