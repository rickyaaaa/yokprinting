<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_table_contains_the_required_business_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('invoices', [
            'id',
            'customer_id',
            'created_by',
            'invoice_number',
            'issue_date',
            'due_date',
            'status',
            'payment_status',
            'production_status',
            'currency',
            'subtotal',
            'discount_type',
            'discount_value',
            'discount_amount',
            'tax_rate',
            'tax_amount',
            'total_amount',
            'shipping_type',
            'shipping_cost',
            'order_process_status',
            'total_hpp',
            'gross_profit',
            'dp_required_percent',
            'notes',
            'terms',
            'design_notes',
            'mockup_url',
            'template',
            'theme_color',
            'metadata',
            'sent_at',
            'viewed_at',
            'paid_at',
            'deleted_at',
        ]));
    }

    public function test_invoice_can_be_created_with_casts_and_soft_deleted(): void
    {
        $creator = User::factory()->create();
        $invoice = Invoice::query()->create([
            'customer_id' => 99,
            'created_by' => $creator->id,
            'invoice_number' => 'INV-2026-0080',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'subtotal' => 21250000,
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'discount_amount' => 1062500,
            'tax_rate' => 11,
            'tax_amount' => 2220625,
            'total_amount' => 22408125,
            'metadata' => ['source' => 'invoice-form'],
        ]);

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(Invoice::PAYMENT_UNPAID, $invoice->payment_status);
        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->production_status);
        $this->assertSame(Invoice::SHIPPING_NONE, $invoice->shipping_type);
        $this->assertSame(Invoice::ORDER_PROCESS_DRAFT, $invoice->order_process_status);
        $this->assertSame('Drafting', $invoice->productionStatusLabel());
        $this->assertSame(11204062.5, $invoice->requiredDpAmount());
        $this->assertSame('21250000.00', $invoice->subtotal);
        $this->assertSame(['source' => 'invoice-form'], $invoice->metadata);
        $this->assertTrue($invoice->issue_date->isSameDay('2026-07-23'));
        $this->assertTrue($invoice->creator->is($creator));

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
    }
}
