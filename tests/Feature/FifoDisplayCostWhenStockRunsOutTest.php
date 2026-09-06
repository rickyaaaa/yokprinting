<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "HPP FIFO" answers "what does the next unit out cost?".
 *
 * While stock remains that is the oldest surviving batch. Once every batch
 * is used up there is no next batch, and the client asked for the last
 * purchase price rather than a weighted average, which lagged behind the
 * newest purchase (reported as 654 while the last PO was 660).
 */
class FifoDisplayCostWhenStockRunsOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_follows_the_batches_down_and_then_holds_the_last_purchase_price(): void
    {
        // The client's own worked example: PO1 1000 @ 540, PO2 1000 @ 550.
        $product = $this->product(['last_purchase_price' => 550, 'average_purchase_cost' => 545]);
        $first = $this->batch($product, '2026-01-01', 1000, 540);
        $second = $this->batch($product, '2026-02-01', 1000, 550);

        $this->assertSame(540.0, $product->fresh()->fifoUnitCost());

        // Sell the first 1000 - the next unit now comes from PO2.
        $first->forceFill(['qty_remaining' => 0])->save();
        $this->assertSame(550.0, $product->fresh()->fifoUnitCost());

        // Sell the rest: stock 0, so it holds at the last purchase price -
        // not the 545 average.
        $second->forceFill(['qty_remaining' => 0])->save();
        $this->assertSame(550.0, $product->fresh()->fifoUnitCost());
    }

    public function test_the_reported_case_shows_the_last_purchase_not_the_average(): void
    {
        $product = $this->product([
            'stock' => 0,
            'average_purchase_cost' => 654,
            'last_purchase_price' => 660,
        ]);

        $this->assertSame(660.0, $product->fifoUnitCost());
    }

    public function test_an_exhausted_batch_never_prices_the_next_unit(): void
    {
        $product = $this->product(['last_purchase_price' => 660, 'average_purchase_cost' => 654]);
        $this->batch($product, '2026-01-01', 1000, 540)->forceFill(['qty_remaining' => 0])->save();

        $this->assertSame(660.0, $product->fresh()->fifoUnitCost());
    }

    public function test_a_product_never_actually_purchased_falls_back_to_its_master_price(): void
    {
        // products.purchase_price is NOT NULL, so there is always a master
        // price to fall back on; the average is never reached in practice and
        // is kept only as a defensive last resort.
        $product = $this->product([
            'last_purchase_price' => null,
            'purchase_price' => 500,
            'average_purchase_cost' => 654,
        ]);

        $this->assertSame(500.0, $product->fifoUnitCost());
    }

    public function test_inventory_valuation_and_shortfall_keep_their_average_basis(): void
    {
        // Regression guard: purchaseCostFallback() is shared with "Nilai
        // persediaan" and "Kekurangan stok". Only the HPP FIFO display was
        // meant to change, so these must still lead with the average.
        $product = $this->product([
            'stock' => 0,
            'average_purchase_cost' => 654,
            'last_purchase_price' => 660,
        ]);

        $this->assertSame(654.0, $product->purchaseCostFallback());
        $this->assertSame(660.0, $product->lastPurchaseCostFallback());
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        static $sequence = 0;
        $sequence++;

        $costs = array_intersect_key($attributes, array_flip([
            'purchase_price', 'last_purchase_price', 'average_purchase_cost',
        ]));

        $product = Product::query()->create([
            'sku' => "FIFO-DISP-{$sequence}",
            'name' => "Produk FIFO {$sequence}",
            'unit' => 'pcs',
            'track_stock' => true,
            ...array_diff_key($attributes, $costs),
        ]);

        // Purchase costs are deliberately not fillable - they are maintained
        // by the purchasing flow (PostGoodsReceipt), which forceFills them.
        if ($costs !== []) {
            $product->forceFill($costs)->save();
        }

        return $product;
    }

    private function batch(Product $product, string $date, float $quantity, float $unitCost): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => $date,
            'qty_received' => $quantity,
            'qty_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'source_type' => 'test',
            'source_reference' => 'FIFO-DISPLAY-TEST',
        ]);
    }
}
