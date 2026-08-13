<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class BuildInvoiceWhatsAppLink
{
    /**
     * Build a customer-ready wa.me link for invoice follow-up.
     */
    public function build(Invoice $invoice, ?string $publicUrl = null, bool $isReminder = false): string
    {
        $invoice->loadMissing(['customer', 'payments']);

        $phone = $this->normalizePhone($invoice->customer?->phone ?? '');
        $message = $this->message($invoice, $publicUrl, $isReminder);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function message(Invoice $invoice, ?string $publicUrl = null, bool $isReminder = false): string
    {
        $invoice->loadMissing(['customer', 'payments']);

        $paidAmount = $invoice->verifiedPaidAmount();
        $remainingAmount = $invoice->remainingAmount();
        $lines = [
            'Halo '.$invoice->customer?->name.',',
            '',
            $isReminder ? 'Pengingat pembayaran dari YokPrinting.ID:' : 'Berikut invoice dari YokPrinting.ID:',
            'Invoice: '.$invoice->invoice_number,
            'Total tagihan: '.$this->rupiah((float) $invoice->total_amount),
            'DP/pembayaran diterima: '.$this->rupiah($paidAmount),
            'Sisa pelunasan: '.$this->rupiah($remainingAmount),
            'Jatuh tempo: '.$invoice->due_date?->locale('id')->translatedFormat('j F Y'),
        ];

        if ($publicUrl) {
            $lines[] = 'Link invoice: '.$publicUrl;
        }

        $lines[] = '';
        $lines[] = $isReminder
            ? 'Mohon konfirmasi apabila pembayaran sudah dilakukan. Terima kasih.'
            : 'Mohon konfirmasi pembayaran/ACC desain agar produksi bisa kami lanjutkan. Terima kasih.';

        return implode("\n", $lines);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits !== '' ? $digits : '6280000000000';
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
