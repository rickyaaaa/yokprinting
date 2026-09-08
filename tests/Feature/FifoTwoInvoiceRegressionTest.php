<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\Inventory\FifoInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client's stated invariant for FIFO costing, locked in with their own
 * numbers: two batches of 1000 at 540 and 550, sold as two invoices of 1000,
 * must cost 540.000 then 550.000 - never averaged to 545.000 each.
 *
 * This also pins the HPP FIFO display around those sales, so the Phase 3
 * change (last purchase price once stock runs out) can never leak into what
 * an already-sold invoice was costed at.
 */
class FifoTwoInvoiceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_sales_draw_their_own_batch_cost_and_are_never_averaged(): void
    {
        $customer = Customer::query()->create(['name' => 'PT FIFO Regresi']);
        $product = Product::query()->create([
            'name' => 'Cup FIFO Regresi',
            'sku' => 'FIFO-REG-01',
            'track_stock' => true,
            'stock' => 2000,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $product->forceFill(['last_purchase_price' => 550, 'average_purchase_cost' => 545])->save();

        $this->batch($product, '2026-08-01', 1000, 540);
        $this->batch($product, '2026-08-10', 1000, 550);

        // Both batches available: the next unit out costs 540.
        $this->assertSame(540.0, $product->fresh()->fifoUnitCost());

        [$firstInvoice, $firstItem] = $this->sale($customer, $product, 'INV-FIFO-REG-1', 1000);
        $firstHpp = app(FifoInventoryService::class)->consume($firstInvoice, $firstItem);
        $this->assertSame(540000.0, $firstHpp);

        // Oldest batch gone, so the next unit now costs 550.
        $this->assertSame(550.0, $product->fresh()->fifoUnitCost());

        [$secondInvoice, $secondItem] = $this->sale($customer, $product, 'INV-FIFO-REG-2', 1000);
        $secondHpp = app(FifoInventoryService::class)->consume($secondInvoice, $secondItem);
        $this->assertSame(550000.0, $secondHpp);

        // Stock is now 0. The display holds at the last purchase price, and
        // that must not rewrite what either invoice was already costed at.
        $this->assertSame('0.0000', $product->fresh()->stock);
        $this->assertSame(550.0, $product->fresh()->fifoUnitCost());
        $this->assertSame(540000.0, $this->costedTotal($firstItem->getKey()));
        $this->assertSame(550000.0, $this->costedTotal($secondItem->getKey()));
    }

    private function costedTotal(int $invoiceItemId): float
    {
        return (float) \DB::table('invoice_item_cost_layers')
            ->where('invoice_item_id', $invoiceItemId)
            ->sum('total_cost');
    }

    /** @return array{0: Invoice, 1: InvoiceItem} */
    private function sale(Customer $customer, Product $product, string $number, float $quantity): array
    {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => $quantity * 1000,
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => 1000,
            'purchase_cost_snapshot' => 0,
            'subtotal' => $quantity * 1000,
            'total_amount' => $quantity * 1000,
        ]);

        return [$invoice, $item];
    }

    private function batch(Product $product, string $date, float $quantity, float $unitCost): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => $date,
            'qty_received' => $quantity,
            'qty_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'source_type' => 'goods_receipt',
            'source_reference' => 'FIFO-REG',
        ]);
    }
}
