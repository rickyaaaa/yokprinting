<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProductOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_products_are_returned_in_name_order(): void
    {
        Product::query()->create([
            'sku' => 'JSA-WEB-03',
            'name' => 'Website Company Profile',
            'category' => 'Pengembangan web',
            'brand' => 'YokPrinting',
            'purchase_price' => 5750000,
            'stock' => null,
        ]);
        Product::query()->create([
            'sku' => 'JSA-BRAND-01',
            'name' => 'Paket Desain Identitas Brand',
            'category' => 'Jasa kreatif',
            'purchase_price' => 6500000,
            'stock' => 4,
            'track_stock' => true,
        ]);
        Product::query()->create([
            'sku' => 'JSA-OLD-01',
            'name' => 'Produk Nonaktif',
            'purchase_price' => 100000,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.products.options.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Paket Desain Identitas Brand')
            ->assertJsonPath('data.0.sku', 'JSA-BRAND-01')
            ->assertJsonMissingPath('data.0.price')
            ->assertJsonPath('data.0.purchase_price', 6500000)
            ->assertJsonPath('data.0.stock', 4)
            ->assertJsonPath('data.0.unit', Product::UNIT_PCS)
            ->assertJsonPath('data.1.name', 'Website Company Profile')
            ->assertJsonPath('data.1.stock', null)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonMissing(['name' => 'Produk Nonaktif']);
    }

    public function test_product_options_can_be_searched_and_filtered_by_selected_ids(): void
    {
        $brand = Product::query()->create([
            'sku' => 'JSA-BRAND-01',
            'name' => 'Paket Desain Identitas Brand',
            'category' => 'Jasa kreatif',
            'purchase_price' => 6500000,
        ]);
        $website = Product::query()->create([
            'sku' => 'JSA-WEB-03',
            'name' => 'Website Company Profile',
            'category' => 'Pengembangan web',
            'purchase_price' => 5750000,
        ]);

        $this->getJson(route('api.products.options.index', ['search' => 'pengembangan']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $website->id);

        $this->getJson(route('api.products.options.index', ['ids' => [$brand->id]]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brand->id);
    }

    public function test_product_option_query_is_validated(): void
    {
        $this->getJson(route('api.products.options.index', [
            'status' => 'archived',
            'limit' => 151,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'limit']);
    }
}
