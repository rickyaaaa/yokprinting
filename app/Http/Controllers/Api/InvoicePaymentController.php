<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Services\Payments\RecordInvoicePayment;
use Illuminate\Http\JsonResponse;

class InvoicePaymentController extends Controller
{
    /**
     * Record a payment for an invoice.
     */
    public function store(
        StorePaymentRequest $request,
        Invoice $invoice,
        RecordInvoicePayment $recordInvoicePayment,
    ): JsonResponse {
        $payment = $recordInvoicePayment->handle(
            $invoice,
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => [
                'id' => $payment->getKey(),
                'payment_number' => $payment->payment_number,
                'invoice_number' => $payment->invoice->invoice_number,
                'payment_date' => $payment->payment_date->toDateString(),
                'method' => $payment->method,
                'method_label' => $payment->methodLabel(),
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'status_label' => $payment->statusLabel(),
                'invoice_payment_status' => $payment->invoice->payment_status,
                'recorded_at' => $payment->created_at->toISOString(),
            ],
        ], 201);
    }
}
