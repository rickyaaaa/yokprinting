<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDeliveryNotePdf
{
    public function generate(Invoice $invoice): GeneratedInvoicePdf
    {
        $invoice->loadMissing(['customer', 'items']);

        // Ensure stable delivery note number is assigned & saved
        $invoice->deliveryNoteNumber();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(
            view('pdf.invoices.delivery-note', ['invoice' => $invoice])->render(),
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
        $path = "delivery-notes/{$pdf->filename}";

        Storage::disk($disk)->put($path, $pdf->contents);

        return $path;
    }

    private function filename(Invoice $invoice): string
    {
        $number = Str::of($invoice->deliveryNoteNumber())
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "surat-jalan-{$number}.pdf";
    }
}
