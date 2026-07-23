<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudApiTest extends TestCase
{
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
            'price' => 6000000,
            'stock' => 42,
        ]);
        Product::query()->create([
            'sku' => 'JSA-BRAND-01',
            'name' => 'Paket desain brand refresh',
            'category_id' => $design->id,
            'category' => $design->name,
            'price' => 12000000,
            'stock' => null,
        ]);
        Product::query()->create([
            'sku' => 'OLD-001',
            'name' => 'Produk nonaktif',
            'category_id' => $premium->id,
            'category' => $premium->name,
            'price' => 100000,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.products.index', [
            'category_id' => $premium->id,
            'sort' => 'price',
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
            'sku' => 'PRM-FLYER-01',
            'name' => 'Flyer promosi bulanan',
            'category_id' => $category->id,
            'unit' => 'rim',
            'price' => 7900000,
            'stock' => 6,
            'minimum_stock' => 12,
            'track_stock' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'PRM-FLYER-01')
            ->assertJsonPath('data.category', 'Materi promosi')
            ->assertJsonPath('data.track_stock', true);

        $productId = $createResponse->json('data.id');

        $this->getJson(route('api.products.show', $productId))
            ->assertOk()
            ->assertJsonPath('data.minimum_stock', 12);

        $this->patchJson(route('api.products.update', $productId), [
            'price' => 8100000,
            'status' => Product::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.price', 8100000)
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
            'price' => 6000000,
        ]);

        $this->postJson(route('api.products.store'), [
            'sku' => 'PRN-CATALOG-01',
            'name' => '',
            'price' => -1,
            'status' => 'archived',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'price', 'status']);

        $this->getJson(route('api.products.index', [
            'status' => 'archived',
            'sort' => 'updated_at',
            'direction' => 'sideways',
            'limit' => 101,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort', 'direction', 'limit']);
    }
}
