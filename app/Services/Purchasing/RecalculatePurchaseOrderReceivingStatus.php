<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;

class RecalculatePurchaseOrderReceivingStatus
{
    /**
     * Re-derive a PO's receiving status purely from its items' current
     * quantity/received_quantity sums. Safe to call after either posting or
     * voiding a goods receipt, since it recomputes from scratch each time
     * rather than applying a directional delta.
     */
    public function handle(int $purchaseOrderId): void
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

        $newStatus = match (true) {
            // Only reachable after voiding every receipt that had contributed
            // to this PO - PostGoodsReceipt itself never drives received back
            // down to zero.
            $totalReceived <= 0 => PurchaseOrder::STATUS_APPROVED,
            $totalReceived >= $totalOrdered => PurchaseOrder::STATUS_FULLY_RECEIVED,
            default => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        };

        if ($newStatus !== $po->status) {
            $po->forceFill(['status' => $newStatus])->save();
        }
    }
}
