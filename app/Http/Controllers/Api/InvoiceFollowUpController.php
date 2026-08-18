<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordInvoiceFollowUpRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\RecordInvoiceFollowUp;
use Illuminate\Http\JsonResponse;

class InvoiceFollowUpController extends Controller
{
    /**
     * Record a follow-up action taken on an invoice's outstanding payment.
     */
    public function store(
        RecordInvoiceFollowUpRequest $request,
        Invoice $invoice,
        RecordInvoiceFollowUp $recordInvoiceFollowUp,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedInvoice = $recordInvoiceFollowUp->handle(
            $invoice,
            $actor,
            $request->validated('note'),
        );

        return response()->json([
            'message' => 'Follow-up berhasil dicatat.',
            'data' => [
                'invoice_number' => $updatedInvoice->invoice_number,
                'last_follow_up_at' => $updatedInvoice->last_follow_up_at?->toISOString(),
                'last_follow_up_note' => $updatedInvoice->last_follow_up_note,
                'last_follow_up_by' => $updatedInvoice->lastFollowUpBy?->name,
            ],
        ]);
    }
}
