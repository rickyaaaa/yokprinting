<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceSentMail;
use App\Models\Invoice;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class InvoiceDeliveryController extends Controller
{
    /**
     * Send a persisted invoice using database-backed invoice and customer data.
     */
    public function send(
        Invoice $invoice,
        MarkInvoiceDelivered $markInvoiceDelivered,
    ): JsonResponse {
        $invoice->loadMissing(['customer', 'items']);
        $recipient = $invoice->customer?->email;

        Validator::make(
            ['recipient' => $recipient],
            ['recipient' => ['required', 'string', 'email:rfc', 'max:254']],
            [
                'recipient.required' => 'Invoice tersimpan belum memiliki email pelanggan.',
                'recipient.email' => 'Email pelanggan pada invoice tersimpan tidak valid.',
            ],
        )->validate();

        $sentMessage = Mail::to($recipient)->send(new InvoiceSentMail($invoice));
        $invoice = $markInvoiceDelivered->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_EMAIL,
            $recipient,
        );

        return response()->json([
            'message' => 'Invoice berhasil dikirim.',
            'data' => [
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $recipient,
                'status' => $invoice->status,
                'message_id' => $sentMessage?->getMessageId(),
                'sent_at' => $invoice->sent_at->toISOString(),
            ],
        ]);
    }
}
