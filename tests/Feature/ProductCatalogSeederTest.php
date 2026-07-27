<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_yokprinting_actual_108_item_catalog(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(108, Product::query()->count());

        $this->assertDatabaseHas('products', [
            'sku' => 'H-001',
            'name' => 'Cup Injection 12Oz Datar (360ml) Natural',
            'category' => 'Cup Injection',
            'unit' => Product::UNIT_PCS,
            'minimum_order_qty' => 1000,
            'package_conversion' => 500,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'H-108',
            'name' => 'LID Bowl 360ml',
            'category' => 'Tutup / Lid',
            'unit' => Product::UNIT_PCS,
            'minimum_order_qty' => 1000,
            'package_conversion' => 500,
        ]);
    }
}
