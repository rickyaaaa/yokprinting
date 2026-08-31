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

    public function test_a_fully_paid_draft_invoice_shows_lunas_and_no_invoice_status_column(): void
    {
        // "Status Pembayaran" is always payment_status-derived - a fully-paid
        // draft genuinely is "Lunas". Invoice.status (draft/sent) is no longer
        // shown at all: it is not a payment fact and no longer gates any
        // report (see Invoice::scopeBusinessTransaction()), so a "Status
        // Invoice" column only confused the client. The field stays in the
        // database and in the row payload for audit / the WhatsApp delivery
        // workflow.
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
            ->assertDontSee('Status invoice')
            ->assertSee("{$escapedQuote}status{$escapedQuote}:{$escapedQuote}Lunas{$escapedQuote}", false)
            ->assertDontSee('invoice_status_label')
            // Raw enum still available to non-UI consumers.
            ->assertSee("{$escapedQuote}invoice_status{$escapedQuote}:{$escapedQuote}draft{$escapedQuote}", false);
    }

    public function test_invoice_list_shows_exactly_two_status_columns(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Dua Kolom']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUA-KOLOM-001',
            'issue_date' => '2026-08-24',
            'due_date' => '2026-09-07',
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 100000,
        ]);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Status pembayaran')
            ->assertSee('Status pesanan')
            ->assertDontSee('Status invoice')
            // The server-side filter no longer offers draft/sent either.
            ->assertDontSee('>Terkirim</option>', false)
            ->assertSee('>Dibatalkan</option>', false);
    }

    public function test_a_legacy_draft_status_filter_link_does_not_blank_the_list(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Legacy Filter']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LEGACY-FILTER-001',
            'issue_date' => '2026-08-24',
            'due_date' => '2026-09-07',
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 100000,
        ]);

        $this->get(route('invoices.index', ['status' => Invoice::STATUS_DRAFT]))
            ->assertOk()
            ->assertSee('INV-LEGACY-FILTER-001');
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
