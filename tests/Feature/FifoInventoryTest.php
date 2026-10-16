<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryDeficitSettlement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FifoInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_consumes_oldest_batches_and_keeps_the_newer_balance(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 1200);
        $this->batch($product, '2026-08-01', 1000, 1000);
        $newer = $this->batch($product, '2026-08-10', 1000, 1200);
        $product->forceFill(['stock' => 2000])->save();

        $hpp = app(FifoInventoryService::class)->consume($invoice, $item);

        $this->assertSame(1240000.0, $hpp);
        $this->assertSame('800.0000', $newer->refresh()->qty_remaining);
        $this->assertSame('800.0000', $product->refresh()->stock);
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 1000,
            'unit_cost' => 1000,
            'total_cost' => 1000000,
        ]);
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 200,
            'unit_cost' => 1200,
            'total_cost' => 240000,
        ]);
    }

    public function test_stock_shortfall_is_allowed_and_uses_the_best_known_cost_for_the_remainder(): void
    {
        // Owner requirement: a sale must never be blocked by insufficient
        // stock. The real batch covers what it can; the remainder is
        // costed at the product's best-known price and stock goes negative.
        [$product, $invoice, $item] = $this->saleContext(quantity: 150);
        $this->batch($product, '2026-08-01', 100, 900);
        $product->forceFill(['stock' => 100, 'average_purchase_cost' => 950])->save();

        $hpp = app(FifoInventoryService::class)->consume($invoice, $item);

        $this->assertSame(137500.0, $hpp); // 100*900 (real batch) + 50*950 (fallback cost)
        $this->assertSame('0.0000', InventoryBatch::query()->where('source_type', '!=', 'deficit')->firstOrFail()->qty_remaining);
        $this->assertSame('-50.0000', $product->refresh()->stock);
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 100,
            'unit_cost' => 900,
            'total_cost' => 90000,
        ]);
        // The shortfall now gets its own cost-layer row against a synthetic
        // "deficit" batch, so a future goods receipt can close it instead of
        // silently double-counting stock. See the deficit-tracking tests below.
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 50,
            'unit_cost' => 950,
            'total_cost' => 47500,
        ]);
        $this->assertSame(2, $item->costLayers()->count());

        $deficitBatch = InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail();
        $this->assertSame('-50.0000', $deficitBatch->qty_remaining);
        $this->assertSame('950.00', $deficitBatch->unit_cost);
    }

    public function test_historical_cost_layer_does_not_change_after_a_new_purchase(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 100);
        $this->batch($product, '2026-08-01', 100, 900);
        $product->forceFill(['stock' => 100])->save();
        $service = app(FifoInventoryService::class);

        $service->consume($invoice, $item);
        $oldHpp = (float) $item->refresh()->hpp_total;

        $this->batch($product, '2026-08-20', 100, 1800);

        $this->assertSame($oldHpp, (float) $item->refresh()->hpp_total);
        $this->assertSame(90000.0, $oldHpp);
    }

    public function test_restoring_an_invoice_is_idempotent_after_its_layers_are_reversed(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 100);
        $batch = $this->batch($product, '2026-08-01', 100, 900);
        $product->forceFill(['stock' => 100])->save();

        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);
        $this->assertTrue($service->hasConsumedInventory($invoice));
        $service->restoreInvoice($invoice);

        $this->assertSame('100.0000', $product->refresh()->stock);
        $this->assertSame('100.0000', $batch->refresh()->qty_remaining);
        $this->assertFalse($service->hasConsumedInventory($invoice->refresh()));

        $service->restoreInvoice($invoice->refresh());

        $this->assertSame('100.0000', $product->refresh()->stock);
        $this->assertSame('100.0000', $batch->refresh()->qty_remaining);
        $this->assertDatabaseCount('inventory_batches', 1);
    }

    public function test_cancelling_an_invoice_after_a_deficit_sale_restores_stock_and_closes_the_deficit(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 150);
        $this->batch($product, '2026-08-01', 100, 900);
        $product->forceFill(['stock' => 100, 'average_purchase_cost' => 950])->save();

        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);
        $this->assertSame('-50.0000', $product->refresh()->stock);

        $service->restoreInvoice($invoice);

        $this->assertSame('100.0000', $product->refresh()->stock);
        $this->assertSame('100.0000', InventoryBatch::query()->where('source_type', '!=', 'deficit')->firstOrFail()->qty_remaining);
        $this->assertSame('0.0000', InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail()->qty_remaining);
    }

    public function test_repeated_restore_after_a_deficit_sale_does_not_double_adjust(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 150);
        $this->batch($product, '2026-08-01', 100, 900);
        $product->forceFill(['stock' => 100, 'average_purchase_cost' => 950])->save();

        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);
        $service->restoreInvoice($invoice);
        $service->restoreInvoice($invoice->refresh());

        $this->assertSame('100.0000', $product->refresh()->stock);
        $this->assertSame('0.0000', InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail()->qty_remaining);
        $this->assertDatabaseCount('inventory_batches', 2); // the real batch + the one deficit batch
    }

    public function test_cancelling_an_invoice_after_a_receipt_closed_its_deficit_creates_costed_compensation_stock(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 50);
        $product->forceFill(['stock' => 0, 'average_purchase_cost' => 950])->save();

        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);
        $this->assertSame('-50.0000', $product->refresh()->stock);

        // A goods receipt arrives and closes the deficit before anyone
        // tries to cancel the original sale.
        InventoryBatch::closeDeficitFor($product->id, 50, [
            'unit_cost' => 1200,
            'source_type' => 'goods_receipt',
            'source_reference' => 'GR-DEFICIT-CLOSE',
            'source_date' => '2026-08-21',
        ]);
        $product->forceFill(['stock' => 0])->save();

        $service->restoreInvoice($invoice);

        $this->assertSame('50.0000', $product->refresh()->stock);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'source_type' => 'deficit_compensation',
            'source_reference' => 'GR-DEFICIT-CLOSE',
            'qty_remaining' => 50,
            'unit_cost' => 1200,
        ]);
        $this->assertDatabaseHas('inventory_deficit_settlements', [
            'source_reference' => 'GR-DEFICIT-CLOSE',
            'quantity' => 50,
        ]);
        $this->assertNotNull(InventoryDeficitSettlement::query()->firstOrFail()->reversed_at);
        $this->assertReconciled($product);
    }

    public function test_fifo_available_plus_deficit_stays_reconciled_with_product_stock_across_sell_receive_sell(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 10);
        $this->batch($product, '2026-08-01', 5, 100);
        $product->forceFill(['stock' => 5, 'average_purchase_cost' => 100])->save();
        $service = app(FifoInventoryService::class);

        // Sell 10 against 5 available -> deficit of 5.
        $service->consume($invoice, $item);
        $this->assertReconciled($product);
        $this->assertSame('-5.0000', $product->refresh()->stock);

        // Receive 10 -> 5 closes the deficit, 5 becomes newly available.
        $leftover = InventoryBatch::closeDeficitFor($product->id, 10);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-15',
            'qty_received' => $leftover,
            'qty_remaining' => $leftover,
            'unit_cost' => 120,
            'source_type' => 'test',
            'source_reference' => 'RECEIVE-TEST',
        ]);
        $product->forceFill(['stock' => round((float) $product->stock + 10, 4)])->save();
        $this->assertSame('5.0000', $product->refresh()->stock);
        $this->assertReconciled($product);

        // Sell 3 more, fully covered by the freshly-available batch - no new deficit.
        $item2 = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 3,
            'unit_price' => 1000,
            'purchase_cost_snapshot' => 0,
            'subtotal' => 3000,
            'total_amount' => 3000,
        ]);
        $service->consume($invoice, $item2);
        $this->assertSame('2.0000', $product->refresh()->stock);
        $this->assertReconciled($product);
    }

    public function test_invoice_sale_is_allowed_when_stock_is_zero_and_already_negative(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 10);
        $product->forceFill(['stock' => 0, 'average_purchase_cost' => 500])->save();
        $service = app(FifoInventoryService::class);

        $service->consume($invoice, $item);
        $this->assertSame('-10.0000', $product->refresh()->stock);

        $second = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 5,
            'unit_price' => 1000,
            'purchase_cost_snapshot' => 0,
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);
        $service->consume($invoice, $second);

        $this->assertSame('-15.0000', $product->refresh()->stock);
        $this->assertSame('-15.0000', InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail()->qty_remaining);
        $this->assertReconciled($product);
    }

    public function test_partial_deficit_settlement_can_be_reversed_without_losing_source_cost(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 10);
        $product->forceFill(['stock' => 0, 'average_purchase_cost' => 500])->save();
        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);

        InventoryBatch::closeDeficitFor($product->id, 4, [
            'unit_cost' => 725,
            'source_type' => 'goods_receipt',
            'source_reference' => 'GR-PARTIAL-001',
            'source_date' => '2026-10-16',
        ]);
        $product->forceFill(['stock' => -6])->save();

        $service->restoreInvoice($invoice);

        $this->assertSame('4.0000', $product->refresh()->stock);
        $this->assertSame('0.0000', InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail()->qty_remaining);
        $this->assertDatabaseHas('inventory_batches', [
            'source_type' => 'deficit_compensation',
            'source_reference' => 'GR-PARTIAL-001',
            'qty_remaining' => 4,
            'unit_cost' => 725,
        ]);
        $this->assertReconciled($product);
    }

    public function test_positive_stock_adjustment_that_closed_a_deficit_is_reversible(): void
    {
        [$product, $invoice, $item] = $this->saleContext(quantity: 10);
        $product->forceFill(['stock' => 0, 'average_purchase_cost' => 500])->save();
        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);

        app(RecordStockMovement::class)->record(
            $product,
            StockMovement::TYPE_ADJUSTMENT,
            6,
            'ADJ-DEFICIT-001',
            'Koreksi stok masuk',
        );

        $service->restoreInvoice($invoice);

        $this->assertSame('6.0000', $product->refresh()->stock);
        $this->assertDatabaseHas('inventory_deficit_settlements', [
            'source_type' => 'stock_adjustment',
            'source_reference' => 'ADJ-DEFICIT-001',
            'quantity' => 6,
            'unit_cost' => 500,
        ]);
        $this->assertDatabaseHas('inventory_batches', [
            'source_type' => 'deficit_compensation',
            'source_reference' => 'ADJ-DEFICIT-001',
            'qty_remaining' => 6,
        ]);
        $this->assertReconciled($product);
    }

    public function test_one_receipt_is_allocated_across_multiple_deficit_sales_and_each_can_reverse_independently(): void
    {
        [$product, $invoiceA, $itemA] = $this->saleContext(quantity: 10);
        $product->forceFill(['stock' => 0, 'average_purchase_cost' => 500])->save();
        $invoiceB = Invoice::query()->create([
            'customer_id' => $invoiceA->customer_id,
            'invoice_number' => 'INV-FIFO-SECOND-'.random_int(1000, 9999),
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 5000,
        ]);
        $itemB = $invoiceB->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 5,
            'unit_price' => 1000,
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);
        $service = app(FifoInventoryService::class);
        $service->consume($invoiceA, $itemA);
        $service->consume($invoiceB, $itemB);

        InventoryBatch::closeDeficitFor($product->id, 12, [
            'unit_cost' => 700,
            'source_type' => 'goods_receipt',
            'source_reference' => 'GR-MULTI-DEFICIT',
            'source_date' => '2026-10-16',
        ]);
        $product->forceFill(['stock' => -3])->save();

        $this->assertSame(10.0, (float) $itemA->costLayers()->firstOrFail()->deficitSettlements()->sum('quantity'));
        $this->assertSame(2.0, (float) $itemB->costLayers()->firstOrFail()->deficitSettlements()->sum('quantity'));

        $service->restoreInvoice($invoiceB);
        $this->assertSame('2.0000', $product->refresh()->stock);
        $this->assertReconciled($product);

        $service->restoreInvoice($invoiceA);
        $this->assertSame('12.0000', $product->refresh()->stock);
        $this->assertSame(12.0, (float) InventoryBatch::query()->where('source_type', 'deficit_compensation')->sum('qty_remaining'));
        $this->assertReconciled($product);
    }

    private function assertReconciled(Product $product): void
    {
        $sum = round((float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'), 4);
        $this->assertSame($sum, round((float) $product->refresh()->stock, 4));
    }

    /** @return array{0: Product, 1: Invoice, 2: InvoiceItem} */
    private function saleContext(float $quantity): array
    {
        $customer = Customer::query()->create(['name' => 'FIFO Customer']);
        $product = Product::query()->create([
            'name' => 'FIFO Product',
            'sku' => 'FIFO-'.random_int(1000, 9999),
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-FIFO-'.random_int(1000, 9999),
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 1000000,
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

        return [$product, $invoice, $item];
    }

    private function batch(Product $product, string $date, float $quantity, float $unitCost): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => $date,
            'qty_received' => $quantity,
            'qty_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'source_type' => 'test',
            'source_reference' => 'FIFO-TEST',
        ]);
    }
}
