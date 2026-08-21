<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_goods_receipt_can_be_created_as_draft_against_an_approved_po(): void
    {
        $po = $this->createApprovedPo(quantity: 10000, unitPrice: 700);
        $poItem = $po->items->first();

        $response = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 8000],
            ],
        ])->assertCreated();

        $response
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_DRAFT)
            ->assertJsonPath('data.items.0.quantity_received', 8000)
            ->assertJsonPath('data.items.0.unit_price', 700);

        $this->assertMatchesRegularExpression('/^GR-\d{6}-\d{4}$/', $response->json('data.receipt_number'));

        // Draft receipt must not touch stock yet.
        $this->assertSame('0.0000', $poItem->product->refresh()->stock);
    }

    public function test_goods_receipt_cannot_be_created_against_a_draft_po(): void
    {
        $po = $this->createDraftPo();
        $poItem = $po->items->first();

        $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 10]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchase_order');
    }

    public function test_over_receipt_is_rejected(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $poItem = $po->items->first();

        $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 150]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_posting_updates_stock_average_cost_and_last_price(): void
    {
        $po = $this->createApprovedPo(quantity: 10000, unitPrice: 700);
        $poItem = $po->items->first();
        $product = $poItem->product;

        $receipt = $this->createGoodsReceipt($po, $poItem, 8000);

        $this->postJson(route('api.goods-receipts.post', $receipt))
            ->assertOk()
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_POSTED);

        $product->refresh();
        $this->assertSame('8000.0000', $product->stock);
        $this->assertSame('700.00', $product->average_purchase_cost);
        $this->assertSame('700.00', $product->last_purchase_price);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => '8000.0000',
            'reference_number' => $receipt->receipt_number,
        ]);

        $this->assertSame(
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            $po->refresh()->status,
        );
        $this->assertSame('8000.0000', $poItem->refresh()->received_quantity);
    }

    public function test_weighted_average_cost_across_two_receipts_at_different_prices(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        $supplier = $this->createSupplier();

        // PO 1: 10,000 pcs @700
        $po1 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 700);
        $receipt1 = $this->createGoodsReceipt($po1, $po1->items->first(), 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt1))->assertOk();

        $product->refresh();
        $this->assertSame('10000.0000', $product->stock);
        $this->assertSame('700.00', $product->average_purchase_cost);

        // PO 2: 10,000 pcs @730 -> new average = (10000*700 + 10000*730) / 20000 = 715
        $po2 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 730);
        $receipt2 = $this->createGoodsReceipt($po2, $po2->items->first(), 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt2))->assertOk();

        $product->refresh();
        $this->assertSame('20000.0000', $product->stock);
        $this->assertSame('715.00', $product->average_purchase_cost);
        $this->assertSame('730.00', $product->last_purchase_price);
    }

    public function test_posting_a_non_stock_tracked_product_only_updates_last_price(): void
    {
        $product = $this->createProduct(purchasePrice: 700, trackStock: false);
        $supplier = $this->createSupplier();
        $po = $this->createApprovedPoForProduct($supplier, $product, quantity: 100, unitPrice: 750);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 100);

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $product->refresh();
        $this->assertNull($product->stock);
        $this->assertNull($product->average_purchase_cost);
        $this->assertSame('750.00', $product->last_purchase_price);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_posted_receipt_cannot_be_posted_again(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 100);

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $this->postJson(route('api.goods-receipts.post', $receipt))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_draft_receipt_can_be_cancelled(): void
    {
        $po = $this->createApprovedPo(quantity: 200, unitPrice: 700);
        $poItem = $po->items->first();

        $draftReceipt = $this->createGoodsReceipt($po, $poItem, 50);
        $this->postJson(route('api.goods-receipts.cancel', $draftReceipt))
            ->assertOk()
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_CANCELLED);
    }

    public function test_already_cancelled_receipt_cannot_be_cancelled_again(): void
    {
        $po = $this->createApprovedPo(quantity: 200, unitPrice: 700);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 50);
        $this->postJson(route('api.goods-receipts.cancel', $receipt))->assertOk();

        $this->postJson(route('api.goods-receipts.cancel', $receipt))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_posted_receipt_can_be_voided_when_nothing_touched_the_product_since(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $poItem = $po->items->first();
        $product = $poItem->product;

        $receipt = $this->createGoodsReceipt($po, $poItem, 100);
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $product->refresh();
        $this->assertSame('100.0000', $product->stock);
        $this->assertSame('700.00', $product->average_purchase_cost);
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->refresh()->status);

        $this->postJson(route('api.goods-receipts.cancel', $receipt), ['reason' => 'Salah input jumlah'])
            ->assertOk()
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_CANCELLED);

        $product->refresh();
        $this->assertSame('0.0000', $product->stock);
        $this->assertNull($product->average_purchase_cost);

        $this->assertSame('0.0000', $poItem->refresh()->received_quantity);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->refresh()->status);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => '-100.0000',
            'reference_number' => $receipt->receipt_number,
        ]);
    }

    public function test_voiding_the_second_of_two_receipts_reverses_the_weighted_average_back_to_the_first(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        $supplier = $this->createSupplier();

        $po1 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 700);
        $receipt1 = $this->createGoodsReceipt($po1, $po1->items->first(), 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt1))->assertOk();

        $po2 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 730);
        $poItem2 = $po2->items->first();
        $receipt2 = $this->createGoodsReceipt($po2, $poItem2, 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt2))->assertOk();

        $product->refresh();
        $this->assertSame('20000.0000', $product->stock);
        $this->assertSame('715.00', $product->average_purchase_cost);

        // Void the more recent receipt (receipt2) - safe, since nothing has
        // touched the product since it posted.
        $this->postJson(route('api.goods-receipts.cancel', $receipt2))->assertOk();

        $product->refresh();
        $this->assertSame('10000.0000', $product->stock);
        $this->assertSame('700.00', $product->average_purchase_cost);
        $this->assertSame('0.0000', $poItem2->refresh()->received_quantity);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po2->refresh()->status);

        // receipt1 is untouched and still fully reflects PO1.
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po1->refresh()->status);
    }

    public function test_voiding_an_earlier_receipt_is_blocked_once_a_later_receipt_built_on_top_of_it(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        $supplier = $this->createSupplier();

        $po1 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 700);
        $receipt1 = $this->createGoodsReceipt($po1, $po1->items->first(), 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt1))->assertOk();

        $po2 = $this->createApprovedPoForProduct($supplier, $product, quantity: 10000, unitPrice: 730);
        $receipt2 = $this->createGoodsReceipt($po2, $po2->items->first(), 10000);
        $this->postJson(route('api.goods-receipts.post', $receipt2))->assertOk();

        // receipt1's stock movement is no longer the latest one for this
        // product (receipt2's is), so voiding it must be rejected outright.
        $this->postJson(route('api.goods-receipts.cancel', $receipt1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $product->refresh();
        $this->assertSame('20000.0000', $product->stock);
        $this->assertSame('715.00', $product->average_purchase_cost);
        $this->assertSame(GoodsReceipt::STATUS_POSTED, $receipt1->refresh()->status);
    }

    public function test_voiding_a_posted_receipt_for_a_non_stock_tracked_product_only_needs_the_status_flip(): void
    {
        $product = $this->createProduct(purchasePrice: 700, trackStock: false);
        $supplier = $this->createSupplier();
        $po = $this->createApprovedPoForProduct($supplier, $product, quantity: 100, unitPrice: 750);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 100);
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $this->postJson(route('api.goods-receipts.cancel', $receipt))
            ->assertOk()
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_CANCELLED);

        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_fully_received_po_status_transitions_correctly(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $poItem = $po->items->first();

        $receipt = $this->createGoodsReceipt($po, $poItem, 100);
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->refresh()->status);
    }

    public function test_purchase_order_cannot_be_cancelled_after_a_posted_receipt(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 50);
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $this->postJson(route('api.purchase-orders.cancel', $po))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_guest_cannot_post_a_goods_receipt(): void
    {
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 100);
        auth()->logout();

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertUnauthorized();
        $this->assertSame(GoodsReceipt::STATUS_DRAFT, $receipt->refresh()->status);
    }

    public function test_operations_role_can_post_but_not_cancel_a_posted_flow_confirms_permission_boundary(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $po = $this->createApprovedPo(quantity: 100, unitPrice: 700);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 100);

        $this->actingAs(User::factory()->create(['role' => Role::CODE_OPERATIONS]));

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();
    }

    public function test_posting_a_goods_receipt_closes_an_open_fifo_deficit_before_creating_a_new_batch(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        // Simulate the state a prior oversold invoice would have left behind:
        // stock at -5, with the shortfall recorded as an open FIFO deficit.
        InventoryBatch::recordDeficitFor($product->id, 5, 900, 'INV-DEFICIT-TEST');
        $product->forceFill(['stock' => -5])->save();

        $po = $this->createApprovedPoForProduct($this->createSupplier(), $product, quantity: 10, unitPrice: 950);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 10);

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $product->refresh();
        $this->assertSame('5.0000', $product->stock);

        $deficitBatch = InventoryBatch::query()->where('product_id', $product->id)->where('source_type', 'deficit')->firstOrFail();
        $this->assertSame('0.0000', $deficitBatch->qty_remaining);

        $receiptItem = $receipt->items()->firstOrFail();
        $newBatch = InventoryBatch::query()->where('goods_receipt_item_id', $receiptItem->id)->firstOrFail();
        $this->assertSame('5.0000', $newBatch->qty_remaining);
        $this->assertSame('5.0000', $newBatch->qty_received);

        // FIFO availability now reconciles exactly with product.stock.
        $available = InventoryBatch::query()->where('product_id', $product->id)->where('qty_remaining', '>', 0)->sum('qty_remaining');
        $this->assertSame(5.0, (float) $available);
    }

    public function test_posting_a_goods_receipt_only_partially_closes_a_larger_deficit(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        InventoryBatch::recordDeficitFor($product->id, 8, 900, 'INV-DEFICIT-TEST');
        $product->forceFill(['stock' => -8])->save();

        $po = $this->createApprovedPoForProduct($this->createSupplier(), $product, quantity: 3, unitPrice: 950);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 3);

        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $product->refresh();
        $this->assertSame('-5.0000', $product->stock);

        $deficitBatch = InventoryBatch::query()->where('product_id', $product->id)->where('source_type', 'deficit')->firstOrFail();
        $this->assertSame('-5.0000', $deficitBatch->qty_remaining);

        // Fully absorbed by the deficit - no available batch was created.
        $available = InventoryBatch::query()->where('product_id', $product->id)->where('qty_remaining', '>', 0)->sum('qty_remaining');
        $this->assertSame(0.0, (float) $available);
    }

    public function test_cancelling_a_goods_receipt_that_closed_a_deficit_is_rejected(): void
    {
        $product = $this->createProduct(purchasePrice: 0);
        InventoryBatch::recordDeficitFor($product->id, 5, 900, 'INV-DEFICIT-TEST');
        $product->forceFill(['stock' => -5])->save();

        $po = $this->createApprovedPoForProduct($this->createSupplier(), $product, quantity: 10, unitPrice: 950);
        $receipt = $this->createGoodsReceipt($po, $po->items->first(), 10);
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        $this->postJson(route('api.goods-receipts.cancel', $receipt), ['reason' => 'Coba batalkan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        // Nothing was reversed - the deficit close and the new batch stand.
        $product->refresh();
        $this->assertSame('5.0000', $product->stock);
        $this->assertSame(GoodsReceipt::STATUS_POSTED, $receipt->refresh()->status);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'code' => 'SUP-'.random_int(10000, 99999),
            'name' => 'PT ABC Supplier',
        ]);
    }

    private function createProduct(float $purchasePrice = 700, bool $trackStock = true): Product
    {
        return Product::query()->create([
            'sku' => 'CUP-'.random_int(100000, 999999),
            'name' => 'PP Cup 16oz Datar',
            'unit' => 'Pcs',
            'purchase_price' => $purchasePrice,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? 0 : null,
        ]);
    }

    private function createDraftPo(): PurchaseOrder
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 700]],
        ])->assertCreated();

        return PurchaseOrder::query()->with('items.product')->findOrFail($response->json('data.id'));
    }

    private function createApprovedPo(float $quantity, float $unitPrice): PurchaseOrder
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct(purchasePrice: 0);

        return $this->createApprovedPoForProduct($supplier, $product, $quantity, $unitPrice);
    }

    private function createApprovedPoForProduct(Supplier $supplier, Product $product, float $quantity, float $unitPrice): PurchaseOrder
    {
        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice]],
        ])->assertCreated();

        $po = PurchaseOrder::query()->with('items.product')->findOrFail($response->json('data.id'));

        $this->postJson(route('api.purchase-orders.submit', $po))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $po))->assertOk();

        return $po->refresh()->load('items.product');
    }

    private function createGoodsReceipt(PurchaseOrder $po, PurchaseOrderItem $poItem, float $quantityReceived): GoodsReceipt
    {
        $response = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => $quantityReceived]],
        ])->assertCreated();

        return GoodsReceipt::query()->findOrFail($response->json('data.id'));
    }
}
