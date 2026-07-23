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
            'price' => 8750000,
            'stock' => null,
        ]);
        Product::query()->create([
            'sku' => 'JSA-BRAND-01',
            'name' => 'Paket Desain Identitas Brand',
            'category' => 'Jasa kreatif',
            'price' => 12500000,
            'stock' => 4,
            'track_stock' => true,
        ]);
        Product::query()->create([
            'sku' => 'JSA-OLD-01',
            'name' => 'Produk Nonaktif',
            'price' => 100000,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.products.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Paket Desain Identitas Brand')
            ->assertJsonPath('data.0.sku', 'JSA-BRAND-01')
            ->assertJsonPath('data.0.price', 12500000)
            ->assertJsonPath('data.0.stock', 4)
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
            'price' => 12500000,
        ]);
        $website = Product::query()->create([
            'sku' => 'JSA-WEB-03',
            'name' => 'Website Company Profile',
            'category' => 'Pengembangan web',
            'price' => 8750000,
        ]);

        $this->getJson(route('api.products.index', ['search' => 'pengembangan']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $website->id);

        $this->getJson(route('api.products.index', ['ids' => [$brand->id]]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brand->id);
    }

    public function test_product_option_query_is_validated(): void
    {
        $this->getJson(route('api.products.index', [
            'status' => 'archived',
            'limit' => 101,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'limit']);
    }
}
