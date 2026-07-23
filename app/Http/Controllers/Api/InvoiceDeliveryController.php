<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceEmailRequest;
use App\Mail\InvoiceSentMail;
use App\Models\Invoice;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class InvoiceDeliveryController extends Controller
{
    /**
     * Send an invoice to the requested email address.
     */
    public function send(
        SendInvoiceEmailRequest $request,
        Invoice $invoice,
        MarkInvoiceDelivered $markInvoiceDelivered,
    ): JsonResponse {
        $recipient = $request->validated('recipient');
        $invoice->loadMissing(['customer', 'items']);

        $sentMessage = Mail::to($recipient)->send(new InvoiceSentMail($invoice));
        $invoice = $markInvoiceDelivered->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_EMAIL,
            $recipient,
        );

        return response()->json([
            'message' => 'Invoice berhasil dikirim.',
            'data' => [
                'invoice_id' => $invoice->invoice_number,
                'recipient' => $recipient,
                'status' => $invoice->status,
                'message_id' => $sentMessage?->getMessageId(),
                'sent_at' => $invoice->sent_at->toISOString(),
            ],
        ]);
    }
}
