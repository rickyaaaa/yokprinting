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

    public function test_customer_page_outstanding_and_total_sales_both_include_active_drafts(): void
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
        // Total transaksi now counts every active invoice, draft included:
        // 40k + 100k = 140k across 2 invoices. See
        // Invoice::scopeBusinessTransaction().
        $this->get(route('customers.show', $customer->code))
            ->assertOk()
            ->assertSee('Rp80.000')
            ->assertSee('Rp140.000')
            ->assertSee('2 invoice aktif');
    }

    public function test_customer_page_excludes_cancelled_invoices_from_every_total(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-OUT-02', 'name' => 'PT Customer Cancelled']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CUST-ACTIVE',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 50000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CUST-CANCELLED',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_CANCELLED,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 999999,
        ]);

        // The cancelled invoice still appears as a ROW (labelled "Dibatalkan")
        // but contributes to none of the summary totals: total transaksi and
        // outstanding both read 50k, and the count says 1.
        $this->get(route('customers.show', $customer->code))
            ->assertOk()
            ->assertSee('Rp50.000')
            ->assertSee('1 invoice aktif')
            ->assertSee('Dibatalkan');
    }
}
