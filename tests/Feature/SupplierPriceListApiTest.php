<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPriceList;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class SupplierPriceListApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_supplier_price_can_be_created(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $response = $this->postJson(route('api.supplier-prices.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => 296,
            'valid_from' => '2026-08-10',
            'valid_until' => '2026-08-12',
            'notes' => 'Harga berlaku 3 hari',
        ])->assertCreated();

        $response
            ->assertJsonPath('data.price', 296)
            ->assertJsonPath('data.valid_from', '2026-08-10')
            ->assertJsonPath('data.valid_until', '2026-08-12')
            ->assertJsonPath('data.notes', 'Harga berlaku 3 hari');

        $this->assertDatabaseHas('supplier_price_lists', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => 296,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'supplier_price', 'action' => 'created',
        ]);
    }

    public function test_creating_new_price_keeps_old_history_records(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');
        $this->createPriceList($supplier, $product, price: 310, validFrom: '2026-08-13', validUntil: '2026-08-16');
        $this->createPriceList($supplier, $product, price: 305, validFrom: '2026-08-17', validUntil: null);

        $this->assertSame(3, SupplierPriceList::query()->where('product_id', $product->id)->count());

        $this->getJson(route('api.supplier-prices.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_active_price_resolver_returns_the_correct_price(): void
    {
        $this->travelTo('2026-08-11');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplier->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('data.price', 296)
            ->assertJsonPath('data.valid_from', '2026-08-10')
            ->assertJsonPath('data.valid_until', '2026-08-12')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_active_price_resolver_picks_the_newest_valid_from_when_several_overlap(): void
    {
        $this->travelTo('2026-08-14');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: null);
        $newer = $this->createPriceList($supplier, $product, price: 310, validFrom: '2026-08-13', validUntil: null);

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplier->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $newer->id)
            ->assertJsonPath('data.price', 310);
    }

    public function test_expired_price_is_not_considered_active(): void
    {
        $this->travelTo('2026-08-15');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $priceList = $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplier->id, 'product_id' => $product->id]))
            ->assertOk()
            // No active quote, but the last known one is still surfaced, flagged expired.
            ->assertJsonPath('data.id', $priceList->id)
            ->assertJsonPath('data.status', 'expired');
    }

    public function test_future_price_is_not_considered_active(): void
    {
        $this->travelTo('2026-08-05');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplier->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'upcoming');
    }

    public function test_prices_from_different_suppliers_do_not_mix(): void
    {
        $this->travelTo('2026-08-11');

        $supplierA = $this->createSupplier('SUP-A');
        $supplierB = $this->createSupplier('SUP-B');
        $product = $this->createProduct();

        $this->createPriceList($supplierA, $product, price: 296, validFrom: '2026-08-10', validUntil: null);
        $this->createPriceList($supplierB, $product, price: 285, validFrom: '2026-08-10', validUntil: null);

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplierA->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('data.price', 296);

        $this->getJson(route('api.supplier-prices.active', ['supplier_id' => $supplierB->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('data.price', 285);
    }

    public function test_purchase_order_uses_supplier_specific_price_suggestion_and_can_override_it(): void
    {
        $this->travelTo('2026-08-11');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $priceList = $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        // User is free to change the suggested unit_price before saving the PO.
        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-11',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1000, 'unit_price' => 290, 'supplier_price_list_id' => $priceList->id],
            ],
        ])->assertCreated();

        $response
            ->assertJsonPath('data.items.0.unit_price', 290)
            ->assertJsonPath('data.items.0.supplier_price_list_id', $priceList->id)
            ->assertJsonPath('data.items.0.supplier_price_list.price', 296);

        $this->assertDatabaseHas('purchase_order_items', [
            'product_id' => $product->id,
            'supplier_price_list_id' => $priceList->id,
            'unit_price' => 290,
        ]);
    }

    public function test_new_supplier_price_does_not_change_existing_po_price(): void
    {
        $this->travelTo('2026-08-11');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $priceList = $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $response = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-11',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1000, 'unit_price' => 296, 'supplier_price_list_id' => $priceList->id],
            ],
        ])->assertCreated();

        $purchaseOrder = PurchaseOrder::query()->findOrFail($response->json('data.id'));

        // Supplier quotes a new price later.
        $this->createPriceList($supplier, $product, price: 350, validFrom: '2026-08-13', validUntil: null);

        $this->getJson(route('api.purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', 296);
    }

    public function test_unused_price_can_be_corrected(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $priceList = $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->putJson(route('api.supplier-prices.update', $priceList), ['price' => 299])
            ->assertOk()
            ->assertJsonPath('data.price', 299);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'supplier_price', 'action' => 'corrected',
        ]);
    }

    public function test_correction_is_blocked_once_the_price_has_been_used_by_a_purchase_order(): void
    {
        $this->travelTo('2026-08-11');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $priceList = $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-11',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 296, 'supplier_price_list_id' => $priceList->id],
            ],
        ])->assertCreated();

        $this->putJson(route('api.supplier-prices.update', $priceList), ['price' => 310])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');

        $this->assertSame(296.0, (float) $priceList->refresh()->price);
    }

    public function test_expired_price_shows_expired_status_via_active_endpoint(): void
    {
        $this->travelTo('2026-08-20');

        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $this->createPriceList($supplier, $product, price: 296, validFrom: '2026-08-10', validUntil: '2026-08-12');

        $this->getJson(route('api.supplier-prices.index', ['product_id' => $product->id, 'status' => 'expired']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'expired');
    }

    public function test_guest_cannot_view_supplier_prices(): void
    {
        auth()->logout();

        $this->getJson(route('api.supplier-prices.index'))->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_create_supplier_price(): void
    {
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $this->postJson(route('api.supplier-prices.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => 296,
            'valid_from' => '2026-08-10',
        ])->assertForbidden();
    }

    public function test_viewer_role_can_view_but_not_create_supplier_price(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create(['role' => Role::CODE_VIEWER]));
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $this->getJson(route('api.supplier-prices.index'))->assertOk();

        $this->postJson(route('api.supplier-prices.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => 296,
            'valid_from' => '2026-08-10',
        ])->assertForbidden();
    }

    public function test_price_must_be_greater_than_zero_and_valid_until_cannot_precede_valid_from(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $this->postJson(route('api.supplier-prices.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => 0,
            'valid_from' => '2026-08-10',
            'valid_until' => '2026-08-05',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'valid_until']);
    }

    private function createSupplier(string $code = 'SUP'): Supplier
    {
        return Supplier::query()->create([
            'code' => $code.'-'.random_int(1000, 9999),
            'name' => 'PT ABC Supplier',
        ]);
    }

    private function createProduct(): Product
    {
        return Product::query()->create([
            'sku' => 'CUP-'.random_int(100000, 999999),
            'name' => 'PP Cup 16oz Datar',
            'unit' => 'Pcs',
        ]);
    }

    private function createPriceList(
        Supplier $supplier,
        Product $product,
        float $price,
        string $validFrom,
        ?string $validUntil,
    ): SupplierPriceList {
        $response = $this->postJson(route('api.supplier-prices.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => $price,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
        ])->assertCreated();

        return SupplierPriceList::query()->findOrFail($response->json('data.id'));
    }
}
