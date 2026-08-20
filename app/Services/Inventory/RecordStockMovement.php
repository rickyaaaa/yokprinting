<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\InventoryBatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordStockMovement
{
    /**
     * Record a signed stock mutation and update the product stock atomically.
     */
    public function record(
        Product $product,
        string $type,
        int|float|string $quantity,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?int $userId = null,
        bool $syncFifo = true,
    ): StockMovement {
        if (! in_array($type, $this->allowedTypes(), true)) {
            throw new InvalidArgumentException("Jenis mutasi stok tidak dikenal: {$type}");
        }

        return DB::transaction(function () use ($product, $type, $quantity, $referenceNumber, $notes, $userId, $syncFifo): StockMovement {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $stockBefore = (float) ($lockedProduct->stock ?? 0);
            $signedQuantity = round((float) $quantity, 4);
            $stockAfter = round($stockBefore + $signedQuantity, 4);

            $movement = StockMovement::query()->create([
                'product_id' => $lockedProduct->getKey(),
                'type' => $type,
                'quantity' => $signedQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            $lockedProduct->forceFill(['stock' => $stockAfter])->save();

            if ($syncFifo && $lockedProduct->track_stock && ! in_array($type, [
                StockMovement::TYPE_PURCHASE,
                StockMovement::TYPE_SALE,
            ], true)) {
                $this->syncFifoAdjustment($lockedProduct, $signedQuantity, $referenceNumber);
            }

            return $movement;
        });
    }

    private function syncFifoAdjustment(Product $product, float $quantity, ?string $referenceNumber): void
    {
        if ($quantity > 0) {
            InventoryBatch::query()->create([
                'product_id' => $product->getKey(),
                'purchase_date' => now()->toDateString(),
                'qty_received' => $quantity,
                'qty_remaining' => $quantity,
                'unit_cost' => (float) ($product->average_purchase_cost
                    ?? $product->last_purchase_price
                    ?? $product->purchase_price
                    ?? 0),
                'source_type' => 'stock_adjustment',
                'source_reference' => $referenceNumber,
            ]);

            return;
        }

        $remaining = abs($quantity);
        $batches = InventoryBatch::query()
            ->where('product_id', $product->getKey())
            ->where('qty_remaining', '>', 0)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = min($remaining, (float) $batch->qty_remaining);
            $batch->decrement('qty_remaining', $consumed);
            $remaining = round($remaining - $consumed, 4);
        }
    }

    /**
     * Record a stock opname by storing only the delta from current stock.
     */
    public function stockOpname(
        Product $product,
        int|float|string $countedStock,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?int $userId = null,
    ): StockMovement {
        $currentStock = (float) ($product->refresh()->stock ?? 0);
        $delta = round((float) $countedStock - $currentStock, 4);

        return $this->record(
            $product,
            StockMovement::TYPE_STOCK_OPNAME,
            $delta,
            $referenceNumber,
            $notes,
            $userId,
        );
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        return [
            StockMovement::TYPE_OPENING_BALANCE,
            StockMovement::TYPE_PURCHASE,
            StockMovement::TYPE_SALE,
            StockMovement::TYPE_ADJUSTMENT,
            StockMovement::TYPE_STOCK_OPNAME,
            StockMovement::TYPE_RETURN,
        ];
    }
}
