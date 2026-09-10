<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;

class SyncPurchaseOrderPaymentStatus
{
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $paidAmount = round((float) $purchaseOrder->purchasePayments()
            ->verified()
            ->sum('amount'), 2, PHP_ROUND_HALF_UP);
        $total = (float) $purchaseOrder->grand_total;
        $status = $paidAmount <= 0
            ? PurchaseOrder::PAYMENT_UNPAID
            : ($paidAmount >= $total ? PurchaseOrder::PAYMENT_PAID : PurchaseOrder::PAYMENT_PARTIAL);

        $purchaseOrder->forceFill([
            'payment_status' => $status,
            'paid_amount' => $paidAmount,
        ])->save();

        return $purchaseOrder;
    }
}
