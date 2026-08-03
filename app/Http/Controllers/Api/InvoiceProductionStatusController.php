<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInvoiceProductionStatusRequest;
use App\Models\Invoice;
use App\Services\Invoices\UpdateInvoiceProductionStatus;
use Illuminate\Http\JsonResponse;

class InvoiceProductionStatusController extends Controller
{
    /**
     * Update an invoice's operational production stage.
     */
    public function update(
        UpdateInvoiceProductionStatusRequest $request,
        Invoice $invoice,
        UpdateInvoiceProductionStatus $updateProductionStatus,
    ): JsonResponse {
        $updatedInvoice = $updateProductionStatus->handle(
            $invoice,
            $request->validated('production_status'),
        );

        return response()->json([
            'message' => 'Status produksi berhasil diperbarui.',
            'data' => [
                'invoice_number' => $updatedInvoice->invoice_number,
                'production_status' => $updatedInvoice->production_status,
                'production_status_label' => $updatedInvoice->productionStatusLabel(),
            ],
        ]);
    }
}
