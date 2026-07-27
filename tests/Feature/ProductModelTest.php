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
            'purchase_price',
            'brand',
            'short_description',
            'minimum_order_qty',
            'package_conversion',
            'length_cm',
            'width_cm',
            'height_cm',
            'weight_gram',
            'minimum_stock',
            'cup_size',
            'cup_model',
            'grammage',
            'screen_printing_color',
            'sides',
            'moq_quantity',
            'order_increment',
            'packaging_unit',
        ]));
        $this->assertFalse(Schema::hasColumn('products', 'price'));
    }

    public function test_product_builds_cup_description_and_validates_moq_increment(): void
    {
        $product = Product::query()->create([
            'name' => 'Sablon Cup 16 Oz Oval',
            'category' => 'Sablon cup F&B',
            'cup_size' => '16 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'sides' => 2,
            'purchase_price' => 650,
            'minimum_order_qty' => 1000,
            'package_conversion' => 1000,
            'packaging_unit' => 'pcs',
        ]);

        $this->assertSame('H-001', $product->sku);
        $this->assertSame(Product::UNIT_PCS, $product->unit);
        $this->assertSame(
            'Sablon Cup 16 Oz Oval (8gr) - 1 Warna (Tinta Hitam - 2 Sisi)',
            $product->cupDescription(),
        );
        $this->assertTrue($product->isValidOrderQuantity(2000));
        $this->assertFalse($product->isValidOrderQuantity(999));
        $this->assertFalse($product->isValidOrderQuantity(1500));
    }
}
