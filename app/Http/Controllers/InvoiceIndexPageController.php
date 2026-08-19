<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;

class InvoiceIndexPageController extends Controller
{
    public function __invoke(): View
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->latest('issue_date')
            ->latest('id')
            ->get();

        $invoiceRows = $invoices->map(function (Invoice $invoice): array {
            [$status, $tone] = $this->status($invoice);

            return [
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'email' => $invoice->customer?->email ?? '-',
                'issue_date' => $invoice->issue_date->format('d M Y'),
                'due_date' => $invoice->due_date->format('d M Y'),
                'amount' => $this->rupiah((float) $invoice->total_amount),
                'status' => $status,
                'tone' => $tone,
                'is_editable' => $invoice->isEditable(),
            ];
        })->values();

        $receivables = $invoices
            ->where('status', Invoice::STATUS_SENT)
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID);
        $outstanding = $receivables
            ->sum(fn (Invoice $invoice): float => max(
                0,
                (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0),
            ));
        $overdueCount = $receivables->filter(fn (Invoice $invoice): bool => $invoice->payment_status !== Invoice::PAYMENT_PAID
            && $invoice->due_date->isPast()
        )->count();

        return view('invoices.index', [
            'invoiceRows' => $invoiceRows,
            'summaryCards' => [
                ['label' => 'Total invoice', 'value' => (string) $invoices->count(), 'caption' => 'Seluruh data tersimpan'],
                ['label' => 'Menunggu bayar', 'value' => $this->rupiah($outstanding), 'caption' => 'Sisa pembayaran terverifikasi'],
                ['label' => 'Lewat tempo', 'value' => (string) $overdueCount, 'caption' => 'Butuh follow-up'],
            ],
        ]);
    }

    /** @return array{string, string} */
    private function status(Invoice $invoice): array
    {
        return match (true) {
            $invoice->status === Invoice::STATUS_CANCELLED => ['Dibatalkan', 'danger'],
            $invoice->payment_status === Invoice::PAYMENT_PAID => ['Lunas', 'success'],
            $invoice->due_date->isPast() => ['Overdue', 'danger'],
            $invoice->payment_status === Invoice::PAYMENT_PARTIAL => ['Parsial', 'info'],
            $invoice->status === Invoice::STATUS_DRAFT => ['Draft', 'brand'],
            default => ['Menunggu', 'warning'],
        };
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
