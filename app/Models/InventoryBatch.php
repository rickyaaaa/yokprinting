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

    /**
     * Pay down any open FIFO deficit for this product before new stock
     * becomes available. Returns the leftover quantity (0 if the receipt
     * was fully absorbed by the deficit) that the caller should turn into
     * a normal available batch.
     */
    public static function closeDeficitFor(int $productId, float $quantity): float
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

        return round($quantity - $closeAmount, 4);
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
}
