<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateInvoicePdf
{
    public function generate(Invoice $invoice): GeneratedInvoicePdf
    {
        $invoice->loadMissing(['customer', 'items']);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(
            view('pdf.invoices.show', ['invoice' => $invoice])->render(),
            'UTF-8',
        );
        $dompdf->render();

        return new GeneratedInvoicePdf(
            contents: $dompdf->output(),
            filename: $this->filename($invoice),
        );
    }

    public function store(Invoice $invoice, string $disk = 'local'): string
    {
        $pdf = $this->generate($invoice);
        $path = "invoices/{$pdf->filename}";

        Storage::disk($disk)->put($path, $pdf->contents);

        return $path;
    }

    private function filename(Invoice $invoice): string
    {
        $invoiceNumber = Str::of($invoice->invoice_number)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "invoice-{$invoiceNumber}.pdf";
    }
}
