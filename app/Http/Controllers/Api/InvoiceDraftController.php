<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceDraftRequest;
use App\Services\Invoices\CreateInvoiceDraft;
use Illuminate\Http\JsonResponse;

class InvoiceDraftController extends Controller
{
    /**
     * Store a new invoice draft.
     */
    public function store(
        StoreInvoiceDraftRequest $request,
        CreateInvoiceDraft $createInvoiceDraft,
    ): JsonResponse {
        $invoice = $createInvoiceDraft->handle(
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Draft invoice berhasil disimpan.',
            'data' => [
                'id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'subtotal' => $invoice->subtotal,
                'discount_amount' => $invoice->discount_amount,
                'tax_amount' => $invoice->tax_amount,
                'total_amount' => $invoice->total_amount,
                'items_count' => $invoice->items->count(),
                'saved_at' => $invoice->updated_at->toISOString(),
            ],
        ], 201);
    }
}
