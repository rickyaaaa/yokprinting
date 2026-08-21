<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

            return $this->recordLocked(
                product: $lockedProduct,
                type: $type,
                quantity: $quantity,
                referenceNumber: $referenceNumber,
                notes: $notes,
                userId: $userId,
                syncFifo: $syncFifo,
            );
        });
    }

    private function recordLocked(
        Product $product,
        string $type,
        int|float|string $quantity,
        ?string $referenceNumber,
        ?string $notes,
        ?int $userId,
        bool $syncFifo,
    ): StockMovement {
        $stockBefore = (float) ($product->stock ?? 0);
        $signedQuantity = round((float) $quantity, 4);
        $stockAfter = round($stockBefore + $signedQuantity, 4);

        // Stock is allowed to go negative (e.g. a sale recorded before the
        // matching purchase/receipt is entered) - the owner explicitly
        // wants invoices to never be blocked by insufficient stock. This
        // only relaxes the raw ledger; FIFO layer adjustments below (for
        // adjustment/opname types) still guard against consuming more than
        // what's actually in a real batch.
        $movement = StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'type' => $type,
            'quantity' => $signedQuantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $product->forceFill(['stock' => $stockAfter])->save();

        if ($syncFifo && $product->track_stock && ! in_array($type, [
            StockMovement::TYPE_PURCHASE,
            StockMovement::TYPE_SALE,
        ], true)) {
            $this->syncFifoAdjustment($product, $signedQuantity, $referenceNumber);
        }

        return $movement;
    }

    private function syncFifoAdjustment(Product $product, float $quantity, ?string $referenceNumber): void
    {
        if ($quantity > 0) {
            // Same as a goods receipt: a manual stock increase (or a stock
            // opname counting more than the ledger shows) pays down any
            // open FIFO deficit first, so FIFO availability stays
            // reconciled with product.stock instead of double-counting.
            $leftover = InventoryBatch::closeDeficitFor($product->getKey(), $quantity);

            if ($leftover > 0) {
                InventoryBatch::query()->create([
                    'product_id' => $product->getKey(),
                    'purchase_date' => now()->toDateString(),
                    'qty_received' => $leftover,
                    'qty_remaining' => $leftover,
                    'unit_cost' => (float) ($product->average_purchase_cost
                        ?? $product->last_purchase_price
                        ?? $product->purchase_price
                        ?? 0),
                    'source_type' => 'stock_adjustment',
                    'source_reference' => $referenceNumber,
                ]);
            }

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

        $available = round((float) $batches->sum('qty_remaining'), 4);

        if ($available < $remaining) {
            throw ValidationException::withMessages([
                'quantity' => "Layer FIFO {$product->name} tidak mencukupi. Tersedia: {$this->formatQuantity($available)}.",
            ]);
        }

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
        return DB::transaction(function () use ($product, $countedStock, $referenceNumber, $notes, $userId): StockMovement {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStock = (float) ($lockedProduct->stock ?? 0);
            $delta = round((float) $countedStock - $currentStock, 4);

            return $this->recordLocked(
                product: $lockedProduct,
                type: StockMovement::TYPE_STOCK_OPNAME,
                quantity: $delta,
                referenceNumber: $referenceNumber,
                notes: $notes,
                userId: $userId,
                syncFifo: true,
            );
        });
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',');
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
