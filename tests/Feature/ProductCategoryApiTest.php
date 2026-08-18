<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class ProductCategoryApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_product_category_schema_and_product_relation_are_available(): void
    {
        foreach (['name', 'slug', 'description', 'status', 'sort_order'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('product_categories', $column),
                "Expected product_categories table to contain [{$column}] column.",
            );
        }

        $this->assertTrue(Schema::hasColumn('products', 'category_id'));

        $category = ProductCategory::query()->create([
            'name' => 'Cetak premium',
            'description' => 'Produk cetak dengan finishing premium.',
        ]);
        $product = Product::query()->create([
            'sku' => 'PRN-CATALOG-01',
            'name' => 'Cetak katalog premium',
            'category_id' => $category->id,
            'category' => $category->name,
            'price' => 6000000,
        ]);

        $this->assertSame('cetak-premium', $category->slug);
        $this->assertTrue($category->products()->whereKey($product)->exists());
        $this->assertTrue($product->categoryModel()->is($category));
    }

    public function test_product_categories_endpoint_returns_active_categories_in_sort_order(): void
    {
        ProductCategory::query()->create([
            'name' => 'Materi promosi',
            'slug' => 'materi-promosi',
            'sort_order' => 20,
        ]);
        ProductCategory::query()->create([
            'name' => 'Jasa desain',
            'slug' => 'jasa-desain',
            'sort_order' => 10,
        ]);
        ProductCategory::query()->create([
            'name' => 'Kategori nonaktif',
            'slug' => 'kategori-nonaktif',
            'status' => ProductCategory::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.product-categories.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Jasa desain')
            ->assertJsonPath('data.1.name', 'Materi promosi')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonMissing(['name' => 'Kategori nonaktif']);
    }

    public function test_product_categories_endpoint_can_search_and_validate_query(): void
    {
        ProductCategory::query()->create([
            'name' => 'Cetak premium',
            'slug' => 'cetak-premium',
        ]);
        ProductCategory::query()->create([
            'name' => 'Jasa desain',
            'slug' => 'jasa-desain',
        ]);

        $this->getJson(route('api.product-categories.index', ['search' => 'premium']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cetak-premium');

        $this->getJson(route('api.product-categories.index', ['limit' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }
}
