<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Models\Product;

class ApplyManualOpeningCost
{
    public function handle(Product $product, float $unitCost): void
    {
        $unitCost = round(max(0, $unitCost), 2);

        $product->forceFill([
            // Keep the legacy field and both reference fallbacks aligned for
            // products whose opening stock was entered manually. Posted GR
            // values remain authoritative and are never overwritten because
            // the controller only calls this service for an explicit change.
            'purchase_price' => $unitCost,
            'last_purchase_price' => $unitCost,
            'average_purchase_cost' => $unitCost,
        ])->save();

        if (! $product->track_stock || (float) ($product->stock ?? 0) <= 0) {
            return;
        }

        $manualLayer = InventoryBatch::query()
            ->where('product_id', $product->getKey())
            ->whereIn('source_type', ['manual_opening', 'opening_balance'])
            ->whereDoesntHave('costLayers')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($manualLayer) {
            $manualLayer->forceFill([
                'qty_received' => $product->stock,
                'qty_remaining' => $product->stock,
                'unit_cost' => $unitCost,
                'source_type' => 'manual_opening',
                'source_reference' => 'MANUAL-OPENING-'.$product->getKey(),
            ])->save();

            return;
        }

        $hasDeficit = InventoryBatch::query()
            ->where('product_id', $product->getKey())
            ->where('source_type', 'deficit')
            ->where('qty_remaining', '<', 0)
            ->exists();

        if ($hasDeficit) {
            // A product with an outstanding oversell must be reconciled via a
            // receipt/adjustment flow; do not hide that deficit with a manual
            // opening layer.
            return;
        }

        InventoryBatch::query()->create([
            'product_id' => $product->getKey(),
            'purchase_date' => now()->toDateString(),
            'qty_received' => $product->stock,
            'qty_remaining' => $product->stock,
            'unit_cost' => $unitCost,
            'source_type' => 'manual_opening',
            'source_reference' => 'MANUAL-OPENING-'.$product->getKey(),
        ]);
    }
}
