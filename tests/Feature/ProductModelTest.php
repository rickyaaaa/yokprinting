<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_contains_yokprinting_cup_spec_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'cup_size',
            'cup_model',
            'grammage',
            'screen_printing_color',
            'sides',
            'moq_quantity',
            'order_increment',
            'packaging_unit',
        ]));
    }

    public function test_product_builds_cup_description_and_validates_moq_increment(): void
    {
        $product = Product::query()->create([
            'sku' => 'CUP-16OV-8G-2S',
            'name' => 'Sablon Cup 16 Oz Oval',
            'category' => 'Sablon cup F&B',
            'cup_size' => '16 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'sides' => 2,
            'price' => 850,
            'moq_quantity' => 1000,
            'order_increment' => 1000,
            'packaging_unit' => 'pcs',
        ]);

        $this->assertSame(
            'Sablon Cup 16 Oz Oval (8gr) - 1 Warna (Tinta Hitam - 2 Sisi)',
            $product->cupDescription(),
        );
        $this->assertTrue($product->isValidOrderQuantity(2000));
        $this->assertFalse($product->isValidOrderQuantity(999));
        $this->assertFalse($product->isValidOrderQuantity(1500));
    }
}
