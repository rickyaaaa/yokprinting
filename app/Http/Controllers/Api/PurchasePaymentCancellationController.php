<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchasePaymentRequest;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Services\Purchasing\CancelPurchasePayment;
use Illuminate\Http\JsonResponse;

class PurchasePaymentCancellationController extends Controller
{
    public function store(
        CancelPurchasePaymentRequest $request,
        PurchasePayment $purchasePayment,
        CancelPurchasePayment $cancelPurchasePayment,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payment = $cancelPurchasePayment->handle(
            $purchasePayment,
            $actor,
            $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Pembayaran PO dibatalkan dan transaksi Kas & Bank ditandai reversed.',
            'data' => [
                'id' => $payment->getKey(),
                'status' => $payment->status,
                'status_label' => $payment->statusLabel(),
                'cash_bank_transaction_status' => $payment->cashBankTransaction?->status,
            ],
        ]);
    }
}
