<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Inventory\FifoInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Reproduces, end-to-end through the real HTTP API (purchase order -> goods
 * receipt -> invoice draft, exactly the flow a real user drives), the exact
 * scenario the client reported as broken:
 *
 *   GR#1: 1000 pcs @ Rp540
 *   GR#2: 1000 pcs @ Rp550
 *   Sale #1: 1000 pcs -> expected HPP 540.000, batch #1 fully consumed
 *   Sale #2: 1000 pcs -> expected HPP 550.000, batch #2 fully consumed
 *
 * This is the mandatory acceptance test for Revision 2 - it must never be
 * "fixed" by relaxing an assertion, only by fixing the underlying cost
 * computation.
 */
class FifoClientAcceptanceScenarioTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_two_batches_of_different_cost_are_consumed_and_costed_strictly_oldest_first(): void
    {
        $product = Product::query()->create([
            'name' => 'Cup Injection 14Oz Datar Black Frosted',
            'sku' => 'CUP-14OZ-BF',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $customer = Customer::query()->create(['name' => 'PT Klien FIFO']);

        $this->receiveGoods($product, quantity: 1000, unitCost: 540);
        $this->receiveGoods($product, quantity: 1000, unitCost: 550);

        $this->assertSame('2000.0000', $product->refresh()->stock);

        // --- Sale #1: 1000 pcs -----------------------------------------
        $invoice1 = $this->createInvoiceDraft($customer, $product, quantity: 1000);
        $item1 = $invoice1->items()->firstOrFail();

        $this->assertSame(540000.0, (float) $item1->refresh()->hpp_total, 'invoice #1 HPP must be 1000 x Rp540');
        $this->assertSame(540.0, (float) $item1->unit_hpp);
        $this->assertSame(540000.0, (float) $invoice1->refresh()->total_hpp);
        $this->assertSame(1, $item1->costLayers()->count(), 'sale #1 must consume batch #1 only, in a single layer');
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item1->id,
            'qty_consumed' => 1000,
            'unit_cost' => 540,
            'total_cost' => 540000,
        ]);
        $this->assertSame('1000.0000', $product->refresh()->stock);

        // --- Available FIFO stock after sale #1: only the Rp550 batch --
        $this->assertSame(1000.0, app(FifoInventoryService::class)->availableQuantity($product->id));
        $availableBatch = InventoryBatch::query()
            ->where('product_id', $product->id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('purchase_date')->orderBy('id')
            ->firstOrFail();
        $this->assertSame('550.00', $availableBatch->unit_cost, 'oldest available batch after sale #1 must be the Rp550 layer');

        // --- Product-list "HPP FIFO" after sale #1 must read 550, never the
        // pre-sale weighted average (545) or any average_purchase_cost value.
        $this->assertSame(550.0, $product->fifoUnitCost());
        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.fifo_hpp', 550);

        // --- Sale #2: 1000 pcs -------------------------------------------
        $invoice2 = $this->createInvoiceDraft($customer, $product, quantity: 1000);
        $item2 = $invoice2->items()->firstOrFail();

        $this->assertSame(550000.0, (float) $item2->refresh()->hpp_total, 'invoice #2 HPP must be 1000 x Rp550');
        $this->assertSame(550.0, (float) $item2->unit_hpp);
        $this->assertSame(550000.0, (float) $invoice2->refresh()->total_hpp);
        $this->assertSame(1, $item2->costLayers()->count(), 'sale #2 must consume batch #2 only, in a single layer');
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item2->id,
            'qty_consumed' => 1000,
            'unit_cost' => 550,
            'total_cost' => 550000,
        ]);

        // --- Final stock = 0, nothing available -------------------------
        $this->assertSame('0.0000', $product->refresh()->stock);
        $this->assertSame(0.0, app(FifoInventoryService::class)->availableQuantity($product->id));
    }

    public function test_partial_fifo_sale_spans_both_batches_with_the_correct_blended_cost(): void
    {
        $product = Product::query()->create([
            'name' => 'Cup Injection 14Oz Datar Black Frosted',
            'sku' => 'CUP-14OZ-BF-2',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $customer = Customer::query()->create(['name' => 'PT Klien FIFO Partial']);

        $this->receiveGoods($product, quantity: 1000, unitCost: 540);
        $this->receiveGoods($product, quantity: 1000, unitCost: 550);

        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1500);
        $item = $invoice->items()->firstOrFail();

        // 1000 x 540 + 500 x 550 = 540.000 + 275.000 = 815.000
        $this->assertSame(815000.0, (float) $item->refresh()->hpp_total);
        $this->assertSame(815000.0, (float) $invoice->refresh()->total_hpp);
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 1000,
            'unit_cost' => 540,
            'total_cost' => 540000,
        ]);
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item->id,
            'qty_consumed' => 500,
            'unit_cost' => 550,
            'total_cost' => 275000,
        ]);
        $this->assertSame('500.0000', $product->refresh()->stock);
    }

    private function receiveGoods(Product $product, float $quantity, float $unitCost): GoodsReceipt
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-'.random_int(10000, 99999),
            'name' => 'Supplier FIFO '.random_int(1000, 9999),
        ]);

        $poResponse = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitCost]],
        ])->assertCreated();

        $po = PurchaseOrder::query()->with('items')->findOrFail($poResponse->json('data.id'));
        $this->postJson(route('api.purchase-orders.submit', $po))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $po))->assertOk();
        $po->refresh()->load('items');

        /** @var PurchaseOrderItem $poItem */
        $poItem = $po->items->first();

        $grResponse = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => $quantity]],
        ])->assertCreated();

        $receipt = GoodsReceipt::query()->findOrFail($grResponse->json('data.id'));
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        return $receipt->refresh();
    }

    private function createInvoiceDraft(Customer $customer, Product $product, float $quantity): Invoice
    {
        $response = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => 1000,
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated();

        return Invoice::query()->with('items')->findOrFail($response->json('data.id'));
    }
}
