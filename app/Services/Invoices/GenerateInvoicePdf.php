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
            view('pdf.invoices.document', ['preview' => $this->previewFor($invoice)])->render(),
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

    /**
     * Generate a PDF directly from the invoice preview snapshot.
     *
     * @param  array<string, mixed>  $preview
     */
    public function generatePreview(array $preview): GeneratedInvoicePdf
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(
            view('pdf.invoices.preview', ['preview' => $preview])->render(),
            'UTF-8',
        );
        $dompdf->render();

        return new GeneratedInvoicePdf(
            contents: $dompdf->output(),
            filename: $this->filenameFromNumber((string) ($preview['invoice_number'] ?? 'preview')),
        );
    }

    private function filename(Invoice $invoice): string
    {
        return $this->filenameFromNumber($invoice->invoice_number);
    }

    /**
     * Normalize a persisted invoice into the exact data contract used by the draft preview.
     *
     * @return array<string, mixed>
     */
    private function previewFor(Invoice $invoice): array
    {
        $totalAmount = (float) $invoice->total_amount;
        $dpAmount = $invoice->requiredDpAmount();

        return [
            'invoice_number' => $invoice->invoice_number,
            'status_label' => $invoice->sent_at ? 'Terkirim' : 'Invoice tersimpan',
            'issue_date_label' => $invoice->issue_date?->locale('id')->translatedFormat('j F Y') ?? '-',
            'currency' => $invoice->currency,
            'customer' => [
                'name' => $invoice->customer?->name ?? 'Pelanggan',
                'email' => $invoice->customer?->email ?? '',
                'phone' => $invoice->customer?->phone ?? '',
                'address' => $invoice->customer?->address ?? '',
            ],
            'items' => $invoice->items->values()->map(function ($item): array {
                $quantity = (float) $item->quantity;
                $unit = 'Pcs';
                $note = collect([
                    $item->sku ? "SKU: {$item->sku}" : null,
                    $item->order_increment ? 'Kelipatan jumlah '.number_format((float) $item->order_increment, 0, ',', '.')." {$unit}" : null,
                ])->filter()->join(' · ');

                return [
                    'name' => $item->description ?: $item->product_name,
                    'note' => $note,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'quantity_label' => rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',')." {$unit}",
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->subtotal,
                ];
            })->all(),
            'subtotal' => (float) $invoice->subtotal,
            'discount_type' => $invoice->discount_type,
            'discount_value' => (float) $invoice->discount_value,
            'discount_amount' => (float) $invoice->discount_amount,
            'tax_enabled' => (float) $invoice->tax_rate > 0,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_amount' => (float) $invoice->tax_amount,
            'shipping_cost' => (float) $invoice->shipping_cost,
            'is_free_shipping' => (bool) $invoice->is_free_shipping,
            'total_amount' => $totalAmount,
            'dp_required_percent' => (float) $invoice->dp_required_percent,
            'dp_amount' => $dpAmount,
            'remaining_amount' => round(max(0, $totalAmount - $dpAmount), 2, PHP_ROUND_HALF_UP),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
        ];
    }

    private function filenameFromNumber(string $invoiceNumber): string
    {
        $invoiceNumber = Str::of($invoiceNumber)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "invoice-{$invoiceNumber}.pdf";
    }
}
