<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\GenerateInvoicePdf;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    /**
     * Generate and download an invoice PDF.
     */
    public function download(
        Invoice $invoice,
        GenerateInvoicePdf $generateInvoicePdf,
        MarkInvoiceDelivered $markInvoiceDelivered,
    ): Response {
        $pdf = $generateInvoicePdf->generate($invoice);
        $markInvoiceDelivered->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_PDF_DOWNLOAD,
        );

        return response($pdf->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$pdf->filename}\"",
            'Content-Length' => (string) strlen($pdf->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
