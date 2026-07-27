<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_items_table_contains_snapshot_and_calculation_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('invoice_items', [
            'id',
            'invoice_id',
            'product_id',
            'product_name',
            'sku',
            'cup_size',
            'cup_model',
            'grammage',
            'screen_printing_color',
            'jenis_cetak',
            'moq_quantity',
            'order_increment',
            'packaging_unit',
            'description',
            'quantity',
            'unit',
            'unit_price',
            'purchase_cost_snapshot',
            'discount_type',
            'discount_value',
            'discount_amount',
            'tax_rate',
            'tax_amount',
            'subtotal',
            'total_amount',
            'sort_order',
            'metadata',
        ]));
    }

    public function test_invoice_item_generates_cup_specification_description(): void
    {
        $invoice = Invoice::query()->create([
            'customer_id' => 99,
            'invoice_number' => 'INV-2026-0083',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
        ]);

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Sablon Cup 16 Oz Oval',
            'cup_size' => '16 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'jenis_cetak' => '2 warna',
            'quantity' => 1000,
            'unit_price' => 850,
            'purchase_cost_snapshot' => 500,
            'subtotal' => 850000,
            'total_amount' => 850000,
        ]);

        $this->assertSame(
            'Sablon Cup 16 Oz Oval (8gr) - 2 warna (Tinta Hitam)',
            $item->refresh()->description,
        );
    }

    public function test_invoice_item_preserves_product_snapshot_and_belongs_to_invoice(): void
    {
        $invoice = Invoice::query()->create([
            'customer_id' => 99,
            'invoice_number' => 'INV-2026-0081',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
        ]);
        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_id' => 42,
            'product_name' => 'Paket Desain Identitas Brand',
            'sku' => 'JSA-BRAND-01',
            'description' => 'Snapshot deskripsi saat invoice dibuat.',
            'quantity' => 2,
            'unit_price' => 12500000,
            'subtotal' => 25000000,
            'total_amount' => 25000000,
            'metadata' => ['category' => 'Jasa kreatif'],
        ]);

        $this->assertSame('Paket Desain Identitas Brand', $item->product_name);
        $this->assertSame('2.0000', $item->quantity);
        $this->assertSame('12500000.00', $item->unit_price);
        $this->assertSame('0.00', $item->refresh()->purchase_cost_snapshot);
        $this->assertSame(['category' => 'Jasa kreatif'], $item->metadata);
        $this->assertTrue($item->invoice->is($invoice));
        $this->assertTrue($invoice->items->contains($item));
    }

    public function test_invoice_items_are_removed_when_invoice_is_force_deleted(): void
    {
        $invoice = Invoice::query()->create([
            'customer_id' => 99,
            'invoice_number' => 'INV-2026-0082',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
        ]);
        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Website Company Profile',
        ]);

        $invoice->forceDelete();

        $this->assertDatabaseMissing('invoice_items', ['id' => $item->id]);
    }
}
