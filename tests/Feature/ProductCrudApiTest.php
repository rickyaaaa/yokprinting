<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class ProductCrudApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_products_can_be_listed_with_search_filters_and_sorting(): void
    {
        $premium = ProductCategory::query()->create(['name' => 'Cetak premium']);
        $design = ProductCategory::query()->create(['name' => 'Jasa desain']);

        Product::query()->create([
            'sku' => 'PRN-CATALOG-01',
            'name' => 'Cetak katalog premium',
            'category_id' => $premium->id,
            'category' => $premium->name,
            'stock' => 42,
        ]);
        Product::query()->create([
            'sku' => 'JSA-BRAND-01',
            'name' => 'Paket desain brand refresh',
            'category_id' => $design->id,
            'category' => $design->name,
            'stock' => null,
        ]);
        Product::query()->create([
            'sku' => 'OLD-001',
            'name' => 'Produk nonaktif',
            'category_id' => $premium->id,
            'category' => $premium->name,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.products.index', [
            'category_id' => $premium->id,
            'sort' => 'purchase_price',
            'direction' => 'desc',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PRN-CATALOG-01')
            ->assertJsonPath('data.0.category_detail.slug', 'cetak-premium')
            ->assertJsonPath('meta.filters.status', Product::STATUS_ACTIVE);

        $this->getJson(route('api.products.index', [
            'search' => 'nonaktif',
            'status' => 'all',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', Product::STATUS_INACTIVE);
    }

    public function test_product_can_be_created_shown_updated_and_soft_deleted(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Materi promosi']);

        $createResponse = $this->postJson(route('api.products.store'), [
            'name' => 'Flyer promosi bulanan',
            'category_id' => $category->id,
            'unit' => Product::UNIT_PCS,
            'brand' => 'YokPrinting',
            'short_description' => 'Flyer promo untuk kampanye bulanan.',
            // purchase_price is intentionally sent here to prove the API
            // silently ignores it - it's no longer an accepted input field.
            'purchase_price' => 4900000,
            'stock' => 6,
            'minimum_stock' => 12,
            'minimum_order_qty' => 500,
            'package_conversion' => 500,
            'length_cm' => 21,
            'width_cm' => 29.7,
            'height_cm' => 1.5,
            'weight_gram' => 750,
            'track_stock' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'H-001')
            ->assertJsonPath('data.unit', Product::UNIT_PCS)
            ->assertJsonPath('data.category', 'Materi promosi')
            ->assertJsonPath('data.brand', 'YokPrinting')
            ->assertJsonPath('data.purchase_price', 0)
            ->assertJsonPath('data.last_purchase_price', null)
            ->assertJsonPath('data.average_purchase_cost', null)
            ->assertJsonPath('data.fifo_hpp', 0)
            ->assertJsonPath('data.package_conversion', 500)
            ->assertJsonPath('data.track_stock', true);

        $productId = $createResponse->json('data.id');

        $this->getJson(route('api.products.show', $productId))
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', 12);

        $this->patchJson(route('api.products.update', $productId), [
            'purchase_price' => 5100000,
            'status' => Product::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.purchase_price', 0)
            ->assertJsonPath('data.status', Product::STATUS_INACTIVE);

        $this->deleteJson(route('api.products.destroy', $productId))
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $productId]);
    }

    public function test_product_payload_and_query_are_validated(): void
    {
        Product::query()->create([
            'sku' => 'PRN-CATALOG-01',
            'name' => 'Cetak katalog premium',
        ]);

        $this->postJson(route('api.products.store'), [
            'sku' => 'PRN-CATALOG-01',
            'name' => '',
            'unit' => 'dus',
            'status' => 'archived',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'unit', 'status']);

        $this->getJson(route('api.products.index', [
            'status' => 'archived',
            'sort' => 'updated_at',
            'direction' => 'sideways',
            'limit' => 151,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort', 'direction', 'limit']);
    }

    public function test_minimum_stock_preserves_zero_and_normalizes_missing_values(): void
    {
        $response = $this->postJson(route('api.products.store'), [
            'name' => 'Produk tanpa batas minimum',
            'minimum_stock' => 0,
            'track_stock' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.minimum_stock', 0);

        $productId = $response->json('data.id');

        $this->getJson(route('api.products.show', $productId))
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', 0);

        $this->patchJson(route('api.products.update', $productId), [
            'name' => 'Produk tanpa batas minimum diperbarui',
        ])
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', 0);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'minimum_stock' => 0,
        ]);

        $this->patchJson(route('api.products.update', $productId), [
            'minimum_stock' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', Product::DEFAULT_MINIMUM_STOCK);

        $this->patchJson(route('api.products.update', $productId), [
            'minimum_stock' => 1500,
        ])
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', 1500);

        $this->postJson(route('api.products.store'), [
            'name' => 'Produk memakai nilai default',
        ])
            ->assertCreated()
            ->assertJsonPath('data.minimum_stock', Product::DEFAULT_MINIMUM_STOCK);

    }
}
