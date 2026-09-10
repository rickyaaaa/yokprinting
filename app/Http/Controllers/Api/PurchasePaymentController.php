<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchasePaymentRequest;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\RecordPurchasePayment;
use Illuminate\Http\JsonResponse;

class PurchasePaymentController extends Controller
{
    public function store(
        StorePurchasePaymentRequest $request,
        PurchaseOrder $purchaseOrder,
        RecordPurchasePayment $recordPurchasePayment,
    ): JsonResponse {
        $payment = $recordPurchasePayment->handle(
            $purchaseOrder,
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Pembayaran PO berhasil dicatat dan Kas & Bank otomatis berkurang.',
            'data' => $this->serialize($payment),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function serialize($payment): array
    {
        return [
            'id' => $payment->getKey(),
            'payment_number' => $payment->payment_number,
            'purchase_order_id' => $payment->purchase_order_id,
            'po_number' => $payment->purchaseOrder?->po_number,
            'payment_date' => $payment->payment_date?->toDateString(),
            'method' => $payment->method,
            'method_label' => $payment->methodLabel(),
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'status_label' => $payment->statusLabel(),
            'cash_bank_transaction_id' => $payment->cashBankTransaction?->getKey(),
            'recorded_by' => $payment->recorder?->name,
            'verified_at' => $payment->verified_at?->toISOString(),
            'cancelled_at' => $payment->cancelled_at?->toISOString(),
            'cancellation_reason' => $payment->cancellation_reason,
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }
}
