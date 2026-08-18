<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_purchase_order_can_be_created_as_draft_with_locked_item_prices(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct(purchasePrice: 700);

        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10000, 'unit_price' => 700],
            ],
            'shipping_cost' => 50000,
        ])->assertCreated();

        $response
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_DRAFT)
            ->assertJsonPath('data.subtotal', 7000000)
            ->assertJsonPath('data.shipping_cost', 50000)
            ->assertJsonPath('data.grand_total', 7050000)
            ->assertJsonPath('data.items.0.unit_price', 700)
            ->assertJsonPath('data.items.0.product_name', $product->name)
            ->assertJsonPath('data.items.0.sku', $product->sku);

        $this->assertMatchesRegularExpression('/^PO-\d{6}-\d{4}$/', $response->json('data.po_number'));
    }

    public function test_guest_cannot_create_a_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        auth()->logout();

        $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100]],
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_create_a_purchase_order(): void
    {
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100]],
        ])->assertForbidden();
    }

    public function test_po_number_is_sequential_within_the_same_month(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $first = $this->createPurchaseOrder($supplier, $product);
        $second = $this->createPurchaseOrder($supplier, $product);

        $this->assertNotSame($first->po_number, $second->po_number);
        $this->assertSame(
            ((int) substr($first->po_number, -4)) + 1,
            (int) substr($second->po_number, -4),
        );
    }

    public function test_price_stays_locked_when_product_purchase_price_changes_later(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct(purchasePrice: 700);
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product, unitPrice: 700);

        $product->update(['purchase_price' => 900]);

        $this->getJson(route('api.purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', 700);
    }

    public function test_draft_purchase_order_can_be_updated(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product, unitPrice: 700, quantity: 100);

        $this->putJson(route('api.purchase-orders.update', $purchaseOrder), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 200, 'unit_price' => 750],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 200)
            ->assertJsonPath('data.items.0.unit_price', 750)
            ->assertJsonPath('data.subtotal', 150000);
    }

    public function test_submitted_purchase_order_cannot_be_updated(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);

        $this->postJson(route('api.purchase-orders.submit', $purchaseOrder))->assertOk();

        $this->putJson(route('api.purchase-orders.update', $purchaseOrder), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_draft_can_be_submitted_then_approved(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);

        $this->postJson(route('api.purchase-orders.submit', $purchaseOrder))
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_WAITING_APPROVAL);

        $this->postJson(route('api.purchase-orders.approve', $purchaseOrder))
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_APPROVED);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'purchase_order', 'action' => 'submitted',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'purchase_order', 'action' => 'approved',
        ]);
    }

    public function test_draft_purchase_order_cannot_be_approved_directly(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);

        $this->postJson(route('api.purchase-orders.approve', $purchaseOrder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_purchase_order_can_be_cancelled_from_draft(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);

        $this->postJson(route('api.purchase-orders.cancel', $purchaseOrder), ['reason' => 'Salah input'])
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_CANCELLED);

        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $purchaseOrder->refresh()->status);
        $this->assertNotNull($purchaseOrder->cancelled_at);
    }

    public function test_already_cancelled_purchase_order_cannot_be_cancelled_again(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);
        $purchaseOrder->forceFill(['status' => PurchaseOrder::STATUS_CANCELLED])->save();

        $this->postJson(route('api.purchase-orders.cancel', $purchaseOrder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_index_lists_and_filters_by_status_and_search(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $draft = $this->createPurchaseOrder($supplier, $product);
        $approved = $this->createPurchaseOrder($supplier, $product);
        $approved->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->save();

        $this->getJson(route('api.purchase-orders.index', ['status' => PurchaseOrder::STATUS_APPROVED]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);

        $this->getJson(route('api.purchase-orders.index', ['search' => $draft->po_number]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id);
    }

    public function test_operations_role_can_submit_but_not_approve(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create(['role' => Role::CODE_OPERATIONS]));
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $purchaseOrder = $this->createPurchaseOrder($supplier, $product);

        $this->postJson(route('api.purchase-orders.submit', $purchaseOrder))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $purchaseOrder))->assertForbidden();
    }

    private function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'code' => 'SUP-'.random_int(1000, 9999),
            'name' => 'PT ABC Supplier',
        ]);
    }

    private function createProduct(float $purchasePrice = 700): Product
    {
        return Product::query()->create([
            'sku' => 'CUP-'.random_int(100000, 999999),
            'name' => 'PP Cup 16oz Datar',
            'unit' => 'Pcs',
            'purchase_price' => $purchasePrice,
        ]);
    }

    private function createPurchaseOrder(
        Supplier $supplier,
        Product $product,
        float $unitPrice = 700,
        float $quantity = 100,
    ): PurchaseOrder {
        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
        ])->assertCreated();

        return PurchaseOrder::query()->findOrFail($response->json('data.id'));
    }
}
