<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = [
        'product_id',
        'goods_receipt_item_id',
        'purchase_date',
        'qty_received',
        'qty_remaining',
        'unit_cost',
        'source_type',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'qty_received' => 'decimal:4',
            'qty_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function costLayers(): HasMany
    {
        return $this->hasMany(InvoiceItemCostLayer::class);
    }

    public function deficitSettlements(): HasMany
    {
        return $this->hasMany(InventoryDeficitSettlement::class);
    }

    /**
     * Pay down any open FIFO deficit for this product before new stock
     * becomes available. Returns the leftover quantity (0 if the receipt
     * was fully absorbed by the deficit) that the caller should turn into
     * a normal available batch.
     */
    public static function closeDeficitFor(int $productId, float $quantity, array $source = []): float
    {
        $deficit = self::query()
            ->where('product_id', $productId)
            ->where('source_type', 'deficit')
            ->where('qty_remaining', '<', 0)
            ->lockForUpdate()
            ->first();

        if (! $deficit) {
            return round($quantity, 4);
        }

        $owed = round(abs((float) $deficit->qty_remaining), 4);
        $closeAmount = min($quantity, $owed);

        $deficit->forceFill([
            'qty_remaining' => round((float) $deficit->qty_remaining + $closeAmount, 4),
        ])->save();

        self::recordDeficitSettlements($deficit, $closeAmount, $source);

        return round($quantity - $closeAmount, 4);
    }

    /**
     * Attribute every deficit closure to the oldest still-open sale layer.
     * This is the provenance restoreInvoice() needs if that sale is later
     * edited or cancelled after stock has already arrived.
     *
     * @param  array<string, mixed>  $source
     */
    private static function recordDeficitSettlements(self $deficit, float $quantity, array $source): void
    {
        $remaining = round($quantity, 4);
        $layers = InvoiceItemCostLayer::query()
            ->where('inventory_batch_id', $deficit->getKey())
            ->whereNull('reversed_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $settled = round((float) InventoryDeficitSettlement::query()
                ->where('invoice_item_cost_layer_id', $layer->getKey())
                ->whereNull('reversed_at')
                ->sum('quantity'), 4);
            $open = max(0, round((float) $layer->qty_consumed - $settled, 4));

            if ($open <= 0) {
                continue;
            }

            $allocated = min($remaining, $open);
            InventoryDeficitSettlement::query()->create([
                'inventory_batch_id' => $deficit->getKey(),
                'invoice_item_cost_layer_id' => $layer->getKey(),
                'goods_receipt_item_id' => $source['goods_receipt_item_id'] ?? null,
                'quantity' => $allocated,
                'unit_cost' => $source['unit_cost'] ?? $layer->unit_cost,
                'source_type' => $source['source_type'] ?? 'legacy_unknown',
                'source_reference' => $source['source_reference'] ?? null,
                'source_date' => $source['source_date'] ?? now()->toDateString(),
            ]);

            $remaining = round($remaining - $allocated, 4);
        }

        // Manually-created legacy deficits may have no invoice cost layer.
        // Preserve the incoming source anyway so the unexplained quantity is
        // visible to audit/reconciliation tooling rather than disappearing.
        if ($remaining > 0) {
            InventoryDeficitSettlement::query()->create([
                'inventory_batch_id' => $deficit->getKey(),
                'invoice_item_cost_layer_id' => null,
                'goods_receipt_item_id' => $source['goods_receipt_item_id'] ?? null,
                'quantity' => $remaining,
                'unit_cost' => $source['unit_cost'] ?? $deficit->unit_cost,
                'source_type' => $source['source_type'] ?? 'legacy_unknown',
                'source_reference' => $source['source_reference'] ?? null,
                'source_date' => $source['source_date'] ?? now()->toDateString(),
            ]);
        }
    }

    /**
     * Add to (or open) the single running FIFO deficit batch for a product
     * when a sale oversells available stock. unit_cost is a weighted
     * average across every deficit-creating sale still outstanding, mirroring
     * how PostGoodsReceipt blends average_purchase_cost - it's purely an
     * informational/reporting figure since each sale's own HPP is captured
     * independently on its InvoiceItemCostLayer row, not read back from here.
     */
    public static function recordDeficitFor(int $productId, float $quantity, float $unitCost, ?string $referenceNumber): self
    {
        $batch = self::query()
            ->where('product_id', $productId)
            ->where('source_type', 'deficit')
            ->lockForUpdate()
            ->first();

        if (! $batch) {
            $batch = self::query()->create([
                'product_id' => $productId,
                'purchase_date' => now()->toDateString(),
                'qty_received' => 0,
                'qty_remaining' => 0,
                'unit_cost' => 0,
                'source_type' => 'deficit',
                'source_reference' => $referenceNumber,
            ]);
        }

        $existingDeficit = round(abs((float) $batch->qty_remaining), 4);
        $newDeficit = round($existingDeficit + $quantity, 4);
        $blendedCost = $existingDeficit > 0
            ? round((($existingDeficit * (float) $batch->unit_cost) + ($quantity * $unitCost)) / $newDeficit, 2)
            : $unitCost;

        $batch->forceFill([
            'qty_remaining' => -$newDeficit,
            'unit_cost' => $blendedCost,
            'source_reference' => $referenceNumber,
        ])->save();

        return $batch;
    }

    /**
     * The unit cost of the oldest available (qty_remaining > 0) batch for a
     * product - i.e. the cost the *next* sale will actually draw from under
     * FIFO. This is the only correct meaning of a "HPP FIFO" display value;
     * it is deliberately NOT a weighted average across remaining batches
     * (that conflates FIFO with average costing - see Product::fifoUnitCost()).
     * Returns null when no batch is currently available, so the caller can
     * apply its own fallback display value.
     */
    public static function oldestAvailableUnitCostFor(int $productId): ?float
    {
        $batch = self::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->first();

        return $batch ? (float) $batch->unit_cost : null;
    }

    /**
     * Total value of a product's currently available FIFO stock - i.e.
     * SUM(qty_remaining * unit_cost) across its available batches. This is
     * a true inventory valuation (distinct from fifoUnitCost()'s per-unit
     * "next sale cost"), used for "Nilai persediaan" style totals. Returns
     * null when no batch is available.
     */
    public static function availableValueFor(int $productId): ?float
    {
        $value = self::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->get()
            ->sum(fn (self $batch): float => (float) $batch->qty_remaining * (float) $batch->unit_cost);

        return $value > 0 ? round($value, 2) : null;
    }
}
