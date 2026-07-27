<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
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
    ): StockMovement {
        if (! in_array($type, $this->allowedTypes(), true)) {
            throw new InvalidArgumentException("Jenis mutasi stok tidak dikenal: {$type}");
        }

        return DB::transaction(function () use ($product, $type, $quantity, $referenceNumber, $notes, $userId): StockMovement {
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

            return $movement;
        });
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
