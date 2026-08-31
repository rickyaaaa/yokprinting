<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;
use ZipArchive;

/**
 * Client-confirmed: stock the company does not have is worth nothing, not
 * negative value. Overselling stays allowed (FifoInventoryService::consume
 * records a deficit batch and lets the invoice through), so the shortfall is
 * reported next to the inventory total instead of being netted off it.
 *
 * Before this, an oversold product fell through fifoInventoryValue()'s
 * fallback branch as `stock * cost` with a negative stock, which is how the
 * product page's "Nilai persediaan" card reached -Rp1.611.635.
 */
class InventoryValuationNeverNegativeTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_oversold_product_is_valued_at_zero_not_negative(): void
    {
        $product = $this->product('OVERSOLD-01', stock: -5500, fallbackCost: 500);
        $this->deficitBatch($product, deficit: 5500, unitCost: 500);

        $this->assertSame(0.0, $product->fifoInventoryValue());
        // The shortfall itself is still reported, just separately.
        $this->assertSame(2750000.0, $product->stockShortfallValue());
        // HPP FIFO keeps using the last known cost - there is no batch left
        // to draw a real cost from, and that is not an error.
        $this->assertSame(500.0, $product->fifoUnitCost());
    }

    public function test_healthy_product_valuation_is_unchanged(): void
    {
        $product = $this->product('HEALTHY-01', stock: 1000, fallbackCost: 660);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => now()->toDateString(),
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 660,
            'source_type' => 'goods_receipt',
        ]);

        $this->assertSame(660000.0, $product->fifoInventoryValue());
        $this->assertSame(0.0, $product->stockShortfallValue());
    }

    public function test_product_with_no_batch_but_positive_stock_still_uses_the_fallback_cost(): void
    {
        $product = $this->product('FALLBACK-01', stock: 200, fallbackCost: 300);

        $this->assertSame(60000.0, $product->fifoInventoryValue());
        $this->assertSame(0.0, $product->stockShortfallValue());
    }

    public function test_summary_card_total_never_goes_negative_and_discloses_the_shortfall(): void
    {
        $oversold = $this->product('CARD-OVERSOLD', stock: -5500, fallbackCost: 500);
        $this->deficitBatch($oversold, deficit: 5500, unitCost: 500);

        $healthy = $this->product('CARD-HEALTHY', stock: 1000, fallbackCost: 660);
        InventoryBatch::query()->create([
            'product_id' => $healthy->id,
            'purchase_date' => now()->toDateString(),
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 660,
            'source_type' => 'goods_receipt',
        ]);

        $cards = collect($this->get(route('products.index'))->assertOk()->viewData('summaryCards'));
        $card = $cards->firstWhere('label', 'Nilai persediaan');

        // Only the healthy product contributes: 1000 x 660. Previously this
        // read Rp-2.090.000 (660.000 + -2.750.000).
        $this->assertSame('Rp660.000', $card['value']);
        $this->assertSame('Kekurangan stok Rp2.750.000 di 1 produk', $card['caption']);
        $this->assertSame('warning', $card['tone']);
    }

    public function test_card_keeps_its_normal_caption_when_nothing_is_oversold(): void
    {
        $product = $this->product('CARD-OK', stock: 1000, fallbackCost: 660);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => now()->toDateString(),
            'qty_received' => 1000,
            'qty_remaining' => 1000,
            'unit_cost' => 660,
            'source_type' => 'goods_receipt',
        ]);

        $card = collect($this->get(route('products.index'))->assertOk()->viewData('summaryCards'))
            ->firstWhere('label', 'Nilai persediaan');

        $this->assertSame('Rp660.000', $card['value']);
        $this->assertSame('Berdasarkan layer HPP FIFO aktif', $card['caption']);
        $this->assertSame('success', $card['tone']);
    }

    public function test_product_export_reports_zero_not_a_negative_inventory_value(): void
    {
        $product = $this->product('EXPORT-OVERSOLD', stock: -5500, fallbackCost: 500);
        $this->deficitBatch($product, deficit: 5500, unitCost: 500);

        $workbook = $this->get(route('api.products.export.excel'))->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'oversold-xlsx-');
        file_put_contents($path, $workbook);

        try {
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path) === true);
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
        } finally {
            @unlink($path);
        }

        $this->assertIsString($sheet);
        $this->assertStringContainsString('EXPORT-OVERSOLD', $sheet);
        // Inventory value is 0, not -2.750.000. The negative stock itself is
        // still reported as-is in the Stok column.
        $this->assertStringNotContainsString('<v>-2750000</v>', $sheet);
        $this->assertStringContainsString('<v>-5500</v>', $sheet);
    }

    private function product(string $sku, float $stock, float $fallbackCost): Product
    {
        $product = Product::query()->create([
            'sku' => $sku,
            'name' => 'Produk '.$sku,
            'category' => 'Cup Injection',
            'price' => 1000,
            'track_stock' => true,
            'stock' => $stock,
            'minimum_stock' => 500,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // average_purchase_cost is guarded (set by the purchasing module, not
        // mass-assignable) - forceFill mirrors how PostGoodsReceipt writes it.
        $product->forceFill(['average_purchase_cost' => $fallbackCost])->save();

        return $product->refresh();
    }

    private function deficitBatch(Product $product, float $deficit, float $unitCost): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => now()->toDateString(),
            'qty_received' => 0,
            'qty_remaining' => -$deficit,
            'unit_cost' => $unitCost,
            'source_type' => 'deficit',
        ]);
    }
}
