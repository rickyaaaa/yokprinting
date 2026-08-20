<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordStockMovement;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelGoodsReceipt
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecalculatePurchaseOrderReceivingStatus $recalculatePurchaseOrderReceivingStatus,
    ) {}

    /**
     * Void a goods receipt.
     *
     * - draft: nothing was ever posted, just flip the status.
     * - posted: reverse stock and average cost per item, but ONLY when this
     *   receipt's own stock movement is still the most recent ledger entry
     *   for that product - i.e. nothing (another receipt, a sale, a manual
     *   adjustment) has touched that product's stock since this one posted.
     *   average_purchase_cost is a moving weighted average, so it can only
     *   be inverted exactly while nothing has been layered on top of it. If
     *   even one item in the receipt fails that check, the whole
     *   cancellation is rejected rather than reversing some items and
     *   leaving others - a half-reversed receipt would be worse than an
     *   un-reversible one.
     */
    public function handle(GoodsReceipt $goodsReceipt, User $actor, ?string $reason): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt, $actor, $reason): GoodsReceipt {
            /** @var GoodsReceipt $locked */
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($goodsReceipt->getKey());

            if ($locked->status === GoodsReceipt::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Penerimaan barang ini sudah dibatalkan sebelumnya.',
                ]);
            }

            if ($locked->status === GoodsReceipt::STATUS_POSTED) {
                $this->reversePostedReceipt($locked, $actor);
            }

            $locked->forceFill([
                'status' => GoodsReceipt::STATUS_CANCELLED,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->activityLogger->record(
                module: 'goods_receipt',
                action: 'cancelled',
                event: 'Goods receipt cancelled',
                description: "Penerimaan barang {$locked->receipt_number} dibatalkan.",
                subject: $locked,
                metadata: array_filter(['reason' => $reason]),
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }

    private function reversePostedReceipt(GoodsReceipt $goodsReceipt, User $actor): void
    {
        // Lock products in the same stable order (by id) PostGoodsReceipt
        // uses, so a concurrent post/void touching overlapping products can
        // never deadlock waiting on each other in opposite order.
        $items = $goodsReceipt->items()->orderBy('product_id')->get();

        // First pass: verify every tracked item is still safely reversible
        // before touching anything. track_stock=false items never had a
        // stock/average-cost impact to reverse (see PostGoodsReceipt), so
        // they're skipped here and in the reversal pass below.
        foreach ($items as $item) {
            $product = Product::query()->whereKey($item->product_id)->firstOrFail();

            if (! $product->track_stock) {
                continue;
            }

            $movement = StockMovement::query()
                ->where('product_id', $product->getKey())
                ->where('reference_number', $goodsReceipt->receipt_number)
                ->where('type', StockMovement::TYPE_PURCHASE)
                ->first();

            if (! $movement) {
                throw ValidationException::withMessages([
                    'status' => "Data pergerakan stok untuk {$product->name} tidak ditemukan. Pembatalan dibatalkan demi keamanan data - hubungi admin.",
                ]);
            }

            $batch = InventoryBatch::query()
                ->where('goods_receipt_item_id', $item->getKey())
                ->first();

            if ($batch && (float) $batch->qty_remaining !== (float) $batch->qty_received) {
                throw ValidationException::withMessages([
                    'status' => "Layer FIFO {$product->name} sudah dipakai transaksi penjualan, jadi penerimaan ini tidak bisa dibatalkan otomatis.",
                ]);
            }

            $latestMovementId = (int) StockMovement::query()
                ->where('product_id', $product->getKey())
                ->max('id');

            if ($latestMovementId !== $movement->getKey()) {
                throw ValidationException::withMessages([
                    'status' => "Stok {$product->name} sudah berubah lagi setelah penerimaan ini (ada transaksi lain menyusul), jadi tidak bisa dibatalkan otomatis. Sesuaikan stok secara manual lewat Penyesuaian Stok jika memang perlu dikoreksi.",
                ]);
            }
        }

        // Second pass: everything checked out, now actually reverse.
        foreach ($items as $item) {
            $this->reverseItem($item, $goodsReceipt, $actor);
            $this->decrementPurchaseOrderItemReceived($item->purchase_order_item_id, (float) $item->quantity_received);
        }

        $this->recalculatePurchaseOrderReceivingStatus->handle($goodsReceipt->purchase_order_id);
    }

    private function reverseItem(GoodsReceiptItem $item, GoodsReceipt $goodsReceipt, User $actor): void
    {
        /** @var Product $product */
        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

        if (! $product->track_stock) {
            return;
        }

        $receivedQuantity = (float) $item->quantity_received;
        $unitPrice = (float) $item->unit_price;
        $currentStock = (float) $product->stock;
        $currentAverageCost = (float) $product->average_purchase_cost;
        $stockBeforeThisReceipt = round($currentStock - $receivedQuantity, 4);

        // Invert PostGoodsReceipt's weighted-average formula exactly - safe
        // because the caller already confirmed nothing has touched this
        // product's stock/cost since this receipt posted:
        //   newAvg = (oldStock*oldAvg + received*unitPrice) / newStock
        //   => oldAvg = (newAvg*newStock - received*unitPrice) / oldStock
        $restoredAverageCost = $stockBeforeThisReceipt > 0
            ? round((($currentAverageCost * $currentStock) - ($receivedQuantity * $unitPrice)) / $stockBeforeThisReceipt, 2)
            : null;

        $this->recordStockMovement->record(
            product: $product,
            type: StockMovement::TYPE_ADJUSTMENT,
            quantity: -$receivedQuantity,
            referenceNumber: $goodsReceipt->receipt_number,
            notes: "Pembatalan penerimaan barang {$goodsReceipt->receipt_number}.",
            userId: $actor->getKey(),
            syncFifo: false,
        );

        $batch = InventoryBatch::query()
            ->where('goods_receipt_item_id', $item->getKey())
            ->first();
        $batch?->delete();

        $product->forceFill(['average_purchase_cost' => $restoredAverageCost])->save();
    }

    private function decrementPurchaseOrderItemReceived(int $purchaseOrderItemId, float $quantityReceived): void
    {
        /** @var PurchaseOrderItem $poItem */
        $poItem = PurchaseOrderItem::query()->whereKey($purchaseOrderItemId)->lockForUpdate()->firstOrFail();

        $poItem->forceFill([
            'received_quantity' => max(0, round((float) $poItem->received_quantity - $quantityReceived, 4)),
        ])->save();
    }
}
