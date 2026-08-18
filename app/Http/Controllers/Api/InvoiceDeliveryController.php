<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\BuildInvoiceWhatsAppLink;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceDeliveryController extends Controller
{
    /**
     * Mark an invoice as sent when its WhatsApp message is opened for the customer.
     */
    public function sendWhatsApp(
        Request $request,
        Invoice $invoice,
        BuildInvoiceWhatsAppLink $buildWhatsAppLink,
        MarkInvoiceDelivered $markInvoiceDelivered,
    ): JsonResponse {
        $purpose = Validator::make(
            $request->all(),
            ['purpose' => ['nullable', 'string', 'in:invoice,reminder']],
        )->validate()['purpose'] ?? 'invoice';
        $invoice->loadMissing(['customer', 'items', 'payments']);
        $recipient = preg_replace('/\D+/', '', $invoice->customer?->phone ?? '') ?? '';

        Validator::make(
            ['recipient' => $recipient],
            ['recipient' => ['required', 'string', 'min:9', 'max:15']],
            ['recipient.required' => 'Invoice tersimpan belum memiliki nomor WhatsApp pelanggan yang valid.'],
        )->validate();

        $whatsAppUrl = $buildWhatsAppLink->build($invoice, null, $purpose === 'reminder');
        $invoice = $markInvoiceDelivered->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_WHATSAPP,
            $recipient,
        );

        return response()->json([
            'message' => 'Invoice ditandai terkirim via WhatsApp.',
            'data' => [
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $recipient,
                'status' => $invoice->status,
                'sent_at' => $invoice->sent_at->toISOString(),
                'purpose' => $purpose,
                'whatsapp_url' => $whatsAppUrl,
            ],
        ]);
    }
}
