<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * "HPP FIFO" must be the unit cost of the oldest AVAILABLE FIFO batch (the
 * one the next sale will actually draw from) - never a weighted average
 * across remaining batches, and never the same number as
 * average_purchase_cost. See Product::fifoUnitCost().
 */
class ProductFifoHppApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_fifo_hpp_falls_back_to_purchase_cost_chain_when_no_batch_is_available(): void
    {
        $response = $this->postJson(route('api.products.store'), [
            'name' => 'Produk baru tanpa penerimaan',
            'track_stock' => true,
        ])->assertCreated();

        // Brand new product: purchase_price defaults to 0, no batches yet.
        $response->assertJsonPath('data.fifo_hpp', 0);
    }

    public function test_fifo_hpp_is_the_oldest_available_batch_cost_not_a_weighted_average(): void
    {
        $product = Product::query()->create([
            'name' => 'Cup Injection 14Oz Datar Black Frosted',
            'sku' => 'CUP-14OZ-BF',
            'track_stock' => true,
            'stock' => 2000,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-01',
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 540,
            'source_type' => 'test',
            'source_reference' => 'GR-1',
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-05',
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 550,
            'source_type' => 'test',
            'source_reference' => 'GR-2',
        ]);

        // Weighted average across both batches would be 545 - the "HPP FIFO"
        // value must be the OLDEST batch's cost (540), not that average.
        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.fifo_hpp', 540)
            ->assertJsonPath('data.fifo_inventory_value', 1090000);

        $this->getJson(route('api.products.index'))
            ->assertOk()
            ->assertJsonPath('data.0.fifo_hpp', 540);
    }

    public function test_fifo_hpp_moves_to_the_next_batch_once_the_oldest_is_exhausted(): void
    {
        $product = Product::query()->create([
            'name' => 'Cup Injection 14Oz Datar Black Frosted',
            'sku' => 'CUP-14OZ-BF-2',
            'track_stock' => true,
            'stock' => 1000,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-01',
            'qty_received' => 1000,
            'qty_remaining' => 0, // fully sold
            'unit_cost' => 540,
            'source_type' => 'test',
            'source_reference' => 'GR-1',
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-05',
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 550,
            'source_type' => 'test',
            'source_reference' => 'GR-2',
        ]);

        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.fifo_hpp', 550);
    }

    public function test_fifo_hpp_ignores_a_negative_deficit_batch_and_uses_fallback(): void
    {
        $product = Product::query()->create([
            'name' => 'Produk minus stok',
            'sku' => 'CUP-DEFICIT',
            'track_stock' => true,
            'stock' => -50,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $product->forceFill(['average_purchase_cost' => 950])->save();
        InventoryBatch::recordDeficitFor($product->id, 50, 950, 'INV-TEST');

        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.fifo_hpp', 950);
    }
}
