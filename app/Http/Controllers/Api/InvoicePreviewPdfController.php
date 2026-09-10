<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoicePreviewPdfRequest;
use App\Services\Invoices\CalculateInvoicePreview;
use App\Services\Invoices\GenerateInvoicePdf;
use Illuminate\Http\Response;

class InvoicePreviewPdfController extends Controller
{
    /**
     * Generate a customer-facing invoice PDF from the current preview snapshot.
     */
    public function __invoke(
        GenerateInvoicePreviewPdfRequest $request,
        CalculateInvoicePreview $calculateInvoicePreview,
        GenerateInvoicePdf $generateInvoicePdf,
    ): Response {
        $input = $request->validated();
        $preview = $calculateInvoicePreview->calculate($input);

        $pdf = $generateInvoicePdf->generatePreview($preview);

        return response($pdf->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$pdf->filename}\"",
            'Content-Length' => (string) strlen($pdf->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
