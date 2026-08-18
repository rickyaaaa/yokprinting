<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class SupplierCrudApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_suppliers_table_and_product_supplier_pivot_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('suppliers', [
            'code',
            'name',
            'contact_person',
            'phone',
            'email',
            'address',
        ]));

        $this->assertTrue(Schema::hasColumns('product_supplier', [
            'product_id',
            'supplier_id',
            'purchase_price',
            'minimum_purchase',
            'supplier_unit',
            'is_primary',
        ]));
    }

    public function test_supplier_can_be_created_listed_shown_updated_and_soft_deleted(): void
    {
        $createResponse = $this->postJson(route('api.suppliers.store'), [
            'code' => 'SUP-001',
            'name' => 'PT Bahan Cup Nusantara',
            'contact_person' => 'Rina',
            'phone' => '+62 21 555 0110',
            'email' => 'sales@bahancup.example',
            'address' => 'Jl. Supplier No. 8, Tangerang',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUP-001')
            ->assertJsonPath('data.name', 'PT Bahan Cup Nusantara')
            ->assertJsonPath('data.contact_person', 'Rina');

        $supplierId = $createResponse->json('data.id');

        $this->getJson(route('api.suppliers.index', ['search' => 'cup']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $supplierId);

        $this->getJson(route('api.suppliers.show', $supplierId))
            ->assertOk()
            ->assertJsonPath('data.email', 'sales@bahancup.example');

        $this->patchJson(route('api.suppliers.update', $supplierId), [
            'name' => 'PT Bahan Cup Indonesia',
            'phone' => '+62 21 555 0111',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'PT Bahan Cup Indonesia')
            ->assertJsonPath('data.phone', '+62 21 555 0111');

        $this->deleteJson(route('api.suppliers.destroy', $supplierId))
            ->assertNoContent();

        $this->assertSoftDeleted('suppliers', ['id' => $supplierId]);
    }

    public function test_supplier_payload_is_validated(): void
    {
        Supplier::query()->create([
            'code' => 'SUP-002',
            'name' => 'PT Supplier Lama',
            'email' => 'sales@supplier.example',
        ]);

        $this->postJson(route('api.suppliers.store'), [
            'code' => 'SUP-002',
            'name' => '',
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'email']);
    }

    public function test_product_and_supplier_have_many_to_many_procurement_relationship(): void
    {
        $product = Product::query()->create([
            'name' => 'Cup 16 Oz Oval 8gr',
            'brand' => 'Orchid',
            'purchase_price' => 650,
            'minimum_order_qty' => 1000,
            'package_conversion' => 500,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-003',
            'name' => 'CV Plastik Food Grade',
        ]);

        $product->suppliers()->attach($supplier->getKey(), [
            'purchase_price' => 625,
            'minimum_purchase' => 5000,
            'supplier_unit' => 'PCS',
            'is_primary' => true,
        ]);

        $product->load('suppliers');
        $supplier->load('products');

        $this->assertTrue($product->suppliers->contains($supplier));
        $this->assertTrue($supplier->products->contains($product));
        $this->assertSame(625.0, (float) $product->suppliers->first()->pivot->purchase_price);
        $this->assertSame(5000, $product->suppliers->first()->pivot->minimum_purchase);
        $this->assertTrue((bool) $supplier->products->first()->pivot->is_primary);
    }
}
