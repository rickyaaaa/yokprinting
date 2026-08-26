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
            });

        // Whitelisted, never fed the raw request value into orderBy().
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'latest';
        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $invoices = $invoices
            ->orderBy('issue_date', $direction)
            ->orderBy('id', $direction)
            ->get();

        $invoiceRows = $invoices->map(function (Invoice $invoice): array {
            [$status, $tone] = $this->status($invoice);
            [$orderStatus, $orderTone] = $this->orderStatus($invoice);
            [$invoiceStatusLabel, $invoiceStatusTone] = $this->invoiceStatusLabel($invoice);

            return [
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'email' => $invoice->customer?->email ?? '-',
                'issue_date' => $invoice->issue_date->format('d M Y'),
                'due_date' => $invoice->due_date->format('d M Y'),
                'amount' => $this->rupiah((float) $invoice->total_amount),
                // "Status Pembayaran" - purely payment_status-derived, never
                // Invoice.status. See status() below.
                'status' => $status,
                'tone' => $tone,
                // "Status Pesanan" - production/order workflow, untouched.
                'order_status' => $orderStatus,
                'order_tone' => $orderTone,
                // "Status Invoice" - draft/sent/cancelled, its own column so
                // it's never confused with (or overwrites) payment status.
                'invoice_status_label' => $invoiceStatusLabel,
                'invoice_status_tone' => $invoiceStatusTone,
                'is_editable' => $invoice->isEditable(),
                // Raw underlying values (Phase 1), kept for API consumers
                // that want the actual enum value rather than a display label.
                'invoice_status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
            ];
        })->values();

        // Same rule as Invoice::scopeReceivable() (Phase 3) - kept in sync
        // by hand here since $invoices is already a loaded Collection, not
        // a query builder, so the scope itself can't be reused directly.
        $receivables = $invoices
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
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
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * "Status Pembayaran" - derived purely from Invoice.payment_status.
     * Invoice.status (draft/sent/cancelled) is a DIFFERENT concept and must
     * never be shown here - see invoiceStatusLabel() for that, and
     * orderStatus() for the separate production/order-workflow column.
     * "Overdue" stays a live-computed (due_date + not-yet-paid) state
     * layered on top, matching the existing convention elsewhere
     * (InvoicePaymentDetailController, CustomerShowPageController) rather
     * than trusting the daily-cron-updated payment_status=overdue column,
     * which can be stale for up to a day right after it becomes true.
     *
     * @return array{string, string}
     */
    private function status(Invoice $invoice): array
    {
        return match (true) {
            $invoice->payment_status === Invoice::PAYMENT_PAID => ['Lunas', 'success'],
            $invoice->due_date->isPast() => ['Overdue', 'danger'],
            $invoice->payment_status === Invoice::PAYMENT_PARTIAL => ['Parsial', 'info'],
            default => ['Belum Bayar', 'warning'],
        };
    }

    /**
     * "Status Invoice" - Invoice.status only (draft/sent/cancelled), shown
     * in its own column so it's never confused with, or overwrites,
     * "Status Pembayaran" above.
     *
     * @return array{string, string}
     */
    private function invoiceStatusLabel(Invoice $invoice): array
    {
        return match ($invoice->status) {
            Invoice::STATUS_DRAFT => ['Draft', 'brand'],
            Invoice::STATUS_CANCELLED => ['Dibatalkan', 'danger'],
            default => ['Terkirim', 'info'],
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
            default => ['Menunggu diproses', 'brand'],
        };
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
