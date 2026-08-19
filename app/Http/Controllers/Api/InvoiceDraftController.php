<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceDraftRequest;
use App\Http\Requests\UpdateInvoiceDraftRequest;
use App\Models\Invoice;
use App\Services\Invoices\CreateInvoiceDraft;
use App\Services\Invoices\UpdateInvoiceDraft;
use Illuminate\Http\JsonResponse;

class InvoiceDraftController extends Controller
{
    /**
     * Store a newly issued invoice.
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
            'message' => 'Invoice berhasil disimpan.',
            'data' => $this->serialize($invoice),
        ], 201);
    }

    /**
     * Replace a draft invoice's header and items. Only allowed while still draft.
     */
    public function update(
        UpdateInvoiceDraftRequest $request,
        Invoice $invoice,
        UpdateInvoiceDraft $updateInvoiceDraft,
    ): JsonResponse {
        $updated = $updateInvoiceDraft->handle(
            $invoice,
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Invoice berhasil diperbarui.',
            'data' => $this->serialize($updated),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(Invoice $invoice): array
    {
        return [
            'id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'sent_at' => $invoice->sent_at?->toISOString(),
            'production_status' => $invoice->production_status,
            'production_status_label' => $invoice->productionStatusLabel(),
            'subtotal' => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'tax_amount' => $invoice->tax_amount,
            'shipping_type' => $invoice->shipping_type,
            'shipping_cost' => $invoice->shipping_cost,
            'is_free_shipping' => $invoice->is_free_shipping,
            'order_process_status' => $invoice->order_process_status,
            'total_hpp' => $invoice->total_hpp,
            'gross_profit' => $invoice->gross_profit,
            'total_amount' => $invoice->total_amount,
            'stock_alerts' => $invoice->metadata['inventory_alerts'] ?? [],
            'items_count' => $invoice->items->count(),
            'saved_at' => $invoice->updated_at->toISOString(),
        ];
    }
}
