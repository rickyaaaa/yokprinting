<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInvoiceRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\CancelInvoice;
use Illuminate\Http\JsonResponse;

class InvoiceCancellationController extends Controller
{
    /**
     * Close an order by cancelling its invoice.
     */
    public function store(
        CancelInvoiceRequest $request,
        Invoice $invoice,
        CancelInvoice $cancelInvoice,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $cancelledInvoice = $cancelInvoice->handle(
            $invoice,
            $actor,
            $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Order berhasil ditutup/dibatalkan.',
            'data' => [
                'invoice_number' => $cancelledInvoice->invoice_number,
                'status' => $cancelledInvoice->status,
                'cancelled_at' => $cancelledInvoice->cancelled_at?->toISOString(),
                'cancellation_reason' => $cancelledInvoice->cancellation_reason,
            ],
        ]);
    }
}
