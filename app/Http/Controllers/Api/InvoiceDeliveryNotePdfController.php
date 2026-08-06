<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\GenerateDeliveryNotePdf;
use Illuminate\Http\Response;

class InvoiceDeliveryNotePdfController extends Controller
{
    /**
     * Generate and download a delivery note / surat jalan PDF.
     */
    public function download(
        Invoice $invoice,
        GenerateDeliveryNotePdf $generateDeliveryNotePdf,
    ): Response {
        if (! $invoice->canGenerateDeliveryNote()) {
            abort(403, 'Surat jalan hanya dapat diunduh jika status produksi Siap Diambil/Kirim atau Lunas & Selesai.');
        }

        $pdf = $generateDeliveryNotePdf->generate($invoice);

        return response($pdf->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$pdf->filename}\"",
            'Content-Length' => (string) strlen($pdf->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
