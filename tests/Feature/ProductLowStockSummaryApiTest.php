<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLowStockSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_summary_returns_only_active_tracked_products_below_minimum(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Materi promosi']);

        Product::query()->create([
            'sku' => 'PRM-FLYER-01',
            'name' => 'Flyer promosi bulanan',
            'category_id' => $category->id,
            'category' => $category->name,
            'unit' => 'rim',
            'price' => 7900000,
            'stock' => 6,
            'minimum_stock' => 12,
            'track_stock' => true,
        ]);
        Product::query()->create([
            'sku' => 'PRN-BANNER-02',
            'name' => 'Banner outdoor premium',
            'category_id' => $category->id,
            'category' => $category->name,
            'unit' => 'meter',
            'price' => 450000,
            'stock' => 120,
            'minimum_stock' => 30,
            'track_stock' => true,
        ]);
        Product::query()->create([
            'sku' => 'PKG-OLD-01',
            'name' => 'Paket nonaktif',
            'price' => 100000,
            'stock' => 0,
            'minimum_stock' => 5,
            'track_stock' => true,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.products.low-stock-summary'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.summary.low_stock_count', 1)
            ->assertJsonPath('data.summary.active_tracked_count', 2)
            ->assertJsonPath('data.summary.healthy_stock_count', 1)
            ->assertJsonPath('data.summary.needs_attention', true)
            ->assertJsonPath('data.products.0.sku', 'PRM-FLYER-01')
            ->assertJsonPath('data.products.0.stock', 6)
            ->assertJsonPath('data.products.0.minimum_stock', 12)
            ->assertJsonPath('data.products.0.shortage', 6)
            ->assertJsonPath('data.products.0.category_detail.slug', 'materi-promosi')
            ->assertJsonMissing(['sku' => 'PKG-OLD-01']);
    }

    public function test_low_stock_summary_can_return_empty_state(): void
    {
        Product::query()->create([
            'sku' => 'PRN-BANNER-02',
            'name' => 'Banner outdoor premium',
            'price' => 450000,
            'stock' => 120,
            'minimum_stock' => 30,
            'track_stock' => true,
        ]);

        $this->getJson(route('api.products.low-stock-summary'))
            ->assertOk()
            ->assertJsonPath('data.summary.low_stock_count', 0)
            ->assertJsonPath('data.summary.active_tracked_count', 1)
            ->assertJsonPath('data.summary.needs_attention', false)
            ->assertJsonCount(0, 'data.products');
    }

    public function test_zero_minimum_stock_is_used_by_the_low_stock_indicator(): void
    {
        Product::query()->create([
            'sku' => 'ZERO-LIMIT-01',
            'name' => 'Produk ambang nol tanpa stok',
            'stock' => 0,
            'minimum_stock' => 0,
            'track_stock' => true,
        ]);
        Product::query()->create([
            'sku' => 'ZERO-LIMIT-02',
            'name' => 'Produk ambang nol dengan stok',
            'stock' => 500,
            'minimum_stock' => 0,
            'track_stock' => true,
        ]);

        $this->getJson(route('api.products.low-stock-summary'))
            ->assertOk()
            ->assertJsonPath('data.summary.low_stock_count', 1)
            ->assertJsonPath('data.products.0.sku', 'ZERO-LIMIT-01')
            ->assertJsonPath('data.products.0.minimum_stock', 0)
            ->assertJsonMissing(['sku' => 'ZERO-LIMIT-02']);
    }

    public function test_null_minimum_stock_uses_the_default_for_stock_indicators(): void
    {
        Product::query()->create([
            'sku' => 'NULL-LIMIT-01',
            'name' => 'Produk memakai ambang default',
            'stock' => 100,
            'minimum_stock' => null,
            'track_stock' => true,
        ]);

        $this->getJson(route('api.products.low-stock-summary'))
            ->assertOk()
            ->assertJsonPath('data.summary.low_stock_count', 1)
            ->assertJsonPath('data.products.0.sku', 'NULL-LIMIT-01')
            ->assertJsonPath('data.products.0.minimum_stock', Product::DEFAULT_MINIMUM_STOCK)
            ->assertJsonPath('data.products.0.shortage', 400);
    }
}
