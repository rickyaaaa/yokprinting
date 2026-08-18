<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostGoodsReceipt
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Post a draft goods receipt: stock, moving-average cost, and the PO's
     * received quantities all change here, and only here. Once posted this
     * is permanent - there's no reversal in this phase, matching the
     * "don't build cancellation-of-posted-records without the actual
     * reversal logic" rule we've followed elsewhere in this codebase.
     */
    public function handle(GoodsReceipt $goodsReceipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt, $actor): GoodsReceipt {
            /** @var GoodsReceipt $locked */
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($goodsReceipt->getKey());

            if ($locked->status !== GoodsReceipt::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya penerimaan barang berstatus draft yang bisa diposting.',
                ]);
            }

            // Lock products in a stable order (by id) across every receipt so
            // two concurrent postings touching overlapping products can never
            // deadlock waiting on each other in opposite order.
            $items = $locked->items()->orderBy('product_id')->get();

            foreach ($items as $item) {
                $this->applyItemToProduct($item->product_id, $item, $locked, $actor);
                $this->incrementPurchaseOrderItemReceived($item->purchase_order_item_id, $item->quantity_received);
            }

            $locked->forceFill([
                'status' => GoodsReceipt::STATUS_POSTED,
                'posted_by' => $actor->getKey(),
                'posted_at' => now(),
            ])->save();

            $this->updatePurchaseOrderReceivingStatus($locked->purchase_order_id);

            $this->activityLogger->record(
                module: 'goods_receipt',
                action: 'posted',
                event: 'Goods receipt posted',
                description: "Penerimaan barang {$locked->receipt_number} diposting.",
                subject: $locked,
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $locked->refresh()->load(['items', 'purchaseOrder']);
        });
    }

    /**
     * Moving weighted-average cost update, following this exact order:
     * lock product -> read stock -> read average cost -> compute new
     * average -> update stock -> update average cost -> update last price
     * -> create stock movement.
     */
    private function applyItemToProduct(
        int $productId,
        GoodsReceiptItem $item,
        GoodsReceipt $goodsReceipt,
        User $actor,
    ): void {
        /** @var Product $product */
        $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        $receivedQuantity = (float) $item->quantity_received;
        $unitPrice = (float) $item->unit_price;

        if (! $product->track_stock) {
            // Not stock-tracked: still worth knowing the latest price paid,
            // but there's no stock ledger to reconcile an average cost against.
            $product->forceFill(['last_purchase_price' => $unitPrice])->save();

            return;
        }

        $oldStock = (float) ($product->stock ?? 0);
        $oldAverageCost = (float) ($product->average_purchase_cost ?? $unitPrice);
        $oldValue = $oldStock * $oldAverageCost;
        $receivedValue = $receivedQuantity * $unitPrice;
        $newStock = round($oldStock + $receivedQuantity, 4);
        $newAverageCost = $newStock > 0
            ? round(($oldValue + $receivedValue) / $newStock, 2)
            : $unitPrice;

        StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'type' => StockMovement::TYPE_PURCHASE,
            'quantity' => $receivedQuantity,
            'stock_before' => $oldStock,
            'stock_after' => $newStock,
            'reference_number' => $goodsReceipt->receipt_number,
            'notes' => "Penerimaan barang {$goodsReceipt->receipt_number}.",
            'user_id' => $actor->getKey(),
        ]);

        $product->forceFill([
            'stock' => $newStock,
            'average_purchase_cost' => $newAverageCost,
            'last_purchase_price' => $unitPrice,
        ])->save();
    }

    private function incrementPurchaseOrderItemReceived(int $purchaseOrderItemId, float $quantityReceived): void
    {
        /** @var PurchaseOrderItem $poItem */
        $poItem = PurchaseOrderItem::query()->whereKey($purchaseOrderItemId)->lockForUpdate()->firstOrFail();

        $poItem->forceFill([
            'received_quantity' => round((float) $poItem->received_quantity + $quantityReceived, 4),
        ])->save();
    }

    private function updatePurchaseOrderReceivingStatus(int $purchaseOrderId): void
    {
        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

        if (! in_array($po->status, [
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_FULLY_RECEIVED,
        ], true)) {
            return;
        }

        $items = $po->items()->get(['quantity', 'received_quantity']);
        $totalOrdered = (float) $items->sum('quantity');
        $totalReceived = (float) $items->sum('received_quantity');

        $newStatus = $totalReceived >= $totalOrdered
            ? PurchaseOrder::STATUS_FULLY_RECEIVED
            : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;

        if ($newStatus !== $po->status) {
            $po->forceFill(['status' => $newStatus])->save();
        }
    }
}
