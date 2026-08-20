<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class RevisionExportApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_stock_export_contains_fifo_value_and_pdf_is_readable(): void
    {
        $product = Product::query()->create([
            'name' => 'Export Product',
            'sku' => 'EXPORT-1',
            'category' => 'Bahan',
            'track_stock' => true,
            'stock' => 10,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-01',
            'qty_received' => 10,
            'qty_remaining' => 10,
            'unit_cost' => 1200,
            'source_type' => 'test',
        ]);

        $query = ['start_date' => '2026-08-01', 'end_date' => '2026-08-20'];
        $csv = $this->get(route('api.reports.stock-mutations.export', $query))->assertOk()->getContent();
        $this->assertStringContainsString('FIFO Inventory Value', $csv);
        $this->assertStringContainsString('12000', $csv);
        $this->assertStringStartsWith('%PDF', $this->get(route('api.reports.stock-mutations.pdf', $query))->assertOk()->getContent());
    }

    public function test_invoice_export_respects_date_filter_for_csv_and_pdf(): void
    {
        $customer = Customer::query()->create(['name' => 'Export Customer']);
        $this->invoice($customer, 'INV-EXPORT-IN', '2026-08-10');
        $this->invoice($customer, 'INV-EXPORT-OUT', '2026-09-10');

        $query = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];
        $csv = $this->get(route('api.invoices.export.csv', $query))->assertOk()->getContent();
        $this->assertStringContainsString('INV-EXPORT-IN', $csv);
        $this->assertStringNotContainsString('INV-EXPORT-OUT', $csv);
        $this->assertStringStartsWith('%PDF', $this->get(route('api.invoices.export.pdf', $query))->assertOk()->getContent());
    }

    private function invoice(Customer $customer, string $number, string $date): void
    {
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => $date,
            'due_date' => $date,
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'subtotal' => 1000,
            'total_amount' => 1000,
        ]);
    }
}
