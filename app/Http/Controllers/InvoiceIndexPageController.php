<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InvoiceIndexPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->date_to))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request): void {
                if (in_array($request->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_CANCELLED], true)) {
                    $query->where('status', $request->status);
                } else {
                    $query->where('payment_status', $request->status);
                }
            })
            ->latest('issue_date')
            ->latest('id')
            ->get();

        $invoiceRows = $invoices->map(function (Invoice $invoice): array {
            [$status, $tone] = $this->status($invoice);
            [$orderStatus, $orderTone] = $this->orderStatus($invoice);

            return [
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'email' => $invoice->customer?->email ?? '-',
                'issue_date' => $invoice->issue_date->format('d M Y'),
                'due_date' => $invoice->due_date->format('d M Y'),
                'amount' => $this->rupiah((float) $invoice->total_amount),
                'status' => $status,
                'tone' => $tone,
                'order_status' => $orderStatus,
                'order_tone' => $orderTone,
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
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'customer_id' => $request->customer_id,
                'status' => $request->status ?? 'all',
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

    /** @return array{string, string} */
    private function orderStatus(Invoice $invoice): array
    {
        $status = $invoice->order_process_status;

        // Older invoices may still have the default order status while their
        // production workflow has already moved forward.
        if ($status === Invoice::ORDER_PROCESS_DRAFT && $invoice->production_status !== Invoice::PRODUCTION_DRAFT) {
            return match ($invoice->production_status) {
                Invoice::PRODUCTION_IN_PRODUCTION => ['Masih produksi', 'info'],
                Invoice::PRODUCTION_COMPLETED => ['Selesai', 'success'],
                Invoice::PRODUCTION_AWAITING_DP => ['Menunggu DP', 'warning'],
                Invoice::PRODUCTION_DESIGN_ACC => ['ACC Mockup/Desain', 'brand'],
                Invoice::PRODUCTION_READY_FOR_PICKUP => ['Siap Diambil/Kirim', 'info'],
                default => ['Drafting', 'brand'],
            };
        }

        return match ($status) {
            Invoice::ORDER_PROCESS_IN_PRODUCTION => ['Masih produksi', 'info'],
            Invoice::ORDER_PROCESS_READY_TO_SHIP => ['Siap dikirim', 'info'],
            Invoice::ORDER_PROCESS_COMPLETED => ['Selesai', 'success'],
            default => ['Draft', 'brand'],
        };
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
