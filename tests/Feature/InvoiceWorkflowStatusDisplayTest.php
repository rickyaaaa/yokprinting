<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class InvoiceWorkflowStatusDisplayTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_index_shows_payment_and_order_workflow_statuses(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PT Workflow Test',
            'email' => 'workflow@example.test',
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-WORKFLOW-001',
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'order_process_status' => Invoice::ORDER_PROCESS_IN_PRODUCTION,
            'total_amount' => 500000,
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-WORKFLOW-002',
            'issue_date' => '2026-08-19',
            'due_date' => '2026-09-02',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'order_process_status' => Invoice::ORDER_PROCESS_COMPLETED,
            'total_amount' => 350000,
        ]);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Status pembayaran')
            ->assertSee('Status pesanan')
            ->assertSee('Parsial')
            ->assertSee('Masih produksi')
            ->assertSee('Lunas')
            ->assertSee('Selesai');
    }

    public function test_a_fully_paid_draft_invoice_shows_lunas_and_draft_in_two_separate_columns(): void
    {
        // Phase 2 (superseding the previous, over-broad fix): "Status
        // Pembayaran" must ALWAYS be payment_status-derived, never
        // Invoice.status - a fully-paid draft genuinely is "Lunas" for
        // payment purposes. Invoice.status="draft" (the fact that it's
        // still excluded from sales/receivables/gross-profit reports until
        // actually sent - Invoice::scopeFinalized()) is real information
        // too, so it gets its OWN "Status Invoice" column instead of
        // overwriting the payment column.
        $customer = Customer::query()->create([
            'name' => 'PT Draft Paid Test',
            'email' => 'draftpaid@example.test',
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DRAFT-PAID-001',
            'issue_date' => '2026-08-24',
            'due_date' => '2026-09-07',
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'total_amount' => 1000000,
        ]);

        // @js() escapes the JSON quotes as " for safe embedding inside
        // the x-data HTML attribute, so that's the literal form to look for.
        $escapedQuote = chr(92).'u0022';

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Status invoice')
            ->assertSee("{$escapedQuote}status{$escapedQuote}:{$escapedQuote}Lunas{$escapedQuote}", false)
            ->assertSee("{$escapedQuote}invoice_status_label{$escapedQuote}:{$escapedQuote}Draft{$escapedQuote}", false);
    }

    public function test_sent_partial_in_production_invoice_shows_parsial_not_draft(): void
    {
        // PHASE 2 test gate, exact scenario from the client brief.
        $customer = Customer::query()->create(['name' => 'PT Phase 2 Gate']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PHASE2-GATE-001',
            'issue_date' => '2026-08-24',
            'due_date' => '2026-09-07',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
            'total_amount' => 300000,
        ]);

        $escapedQuote = chr(92).'u0022';

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee("{$escapedQuote}status{$escapedQuote}:{$escapedQuote}Parsial{$escapedQuote}", false)
            ->assertSee("{$escapedQuote}order_status{$escapedQuote}:{$escapedQuote}Masih produksi{$escapedQuote}", false)
            ->assertDontSee("{$escapedQuote}status{$escapedQuote}:{$escapedQuote}Draft{$escapedQuote}", false);
    }
}
