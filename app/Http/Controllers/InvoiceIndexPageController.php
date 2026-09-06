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
            // Draft/sent is no longer a user-facing concept (it is not a
            // payment fact and no longer gates any report), so the filter only
            // offers payment statuses plus "Dibatalkan" - the one invoice-level
            // state still worth finding, since those rows count toward nothing.
            // A legacy ?status=draft/sent link falls through to "all" rather
            // than silently matching no payment_status and showing an empty
            // list.
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request): void {
                if ($request->status === Invoice::STATUS_CANCELLED) {
                    $query->where('status', Invoice::STATUS_CANCELLED);
                } elseif (! in_array($request->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT], true)) {
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
                'is_editable' => $invoice->isEditable(),
                // Drives the muted/struck-through row styling, so a cancelled
                // invoice is obvious at a glance and not only from reading its
                // two status badges.
                'is_cancelled' => $invoice->status === Invoice::STATUS_CANCELLED,
                // Raw underlying values, kept for consumers that want the
                // actual enum rather than a display label. Invoice.status
                // stays available here (and in the database) for audit and
                // the WhatsApp delivery workflow - it is simply no longer
                // rendered as a user-facing column.
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
        // "Menunggu bayar" deliberately covers only invoices that have had no
        // payment at all - an invoice with a DP already recorded is in
        // progress and is tracked on the Piutang page instead. For these rows
        // nothing has been received, so the full invoice nominal is what is
        // still waiting to be paid.
        $awaitingPayment = $receivables
            ->filter(fn (Invoice $invoice): bool => (float) ($invoice->verified_paid_amount ?? 0) <= 0);
        $outstanding = $awaitingPayment->sum(fn (Invoice $invoice): float => (float) $invoice->total_amount);
        $overdueCount = $receivables->filter(fn (Invoice $invoice): bool => $invoice->payment_status !== Invoice::PAYMENT_PAID
            && $invoice->due_date->isPast()
        )->count();

        return view('invoices.index', [
            'invoiceRows' => $invoiceRows,
            'summaryCards' => [
                ['label' => 'Total invoice', 'value' => (string) $invoices->count(), 'caption' => 'Seluruh data tersimpan'],
                ['label' => 'Menunggu bayar', 'value' => $this->rupiah($outstanding), 'caption' => $awaitingPayment->count().' invoice belum ada pembayaran'],
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
     * "Status Pembayaran" - derived from Invoice.payment_status, except for a
     * cancelled invoice.
     *
     * Cancellation overrides both status columns. A cancelled invoice is owed
     * nothing and produced nothing: CancelInvoice restores its FIFO stock and
     * scopeBusinessTransaction()/scopeReceivable() exclude it from every
     * total. Leaving its stale payment_status/production_status on screen read
     * as "Belum Bayar" and "Menunggu DP" - a bill still to chase and a job
     * still to run, both untrue. It mattered more once the separate "Status
     * Invoice" column was removed, since that was the only other place
     * cancellation showed.
     *
     * "Overdue" stays a live-computed (due_date + not-yet-paid) state layered
     * on top, matching the existing convention elsewhere
     * (InvoicePaymentDetailController, CustomerShowPageController) rather than
     * trusting the daily-cron-updated payment_status=overdue column, which can
     * be stale for up to a day right after it becomes true.
     *
     * @return array{string, string}
     */
    private function status(Invoice $invoice): array
    {
        return match (true) {
            $invoice->status === Invoice::STATUS_CANCELLED => ['Dibatalkan', 'danger'],
            $invoice->payment_status === Invoice::PAYMENT_PAID => ['Lunas', 'success'],
            $invoice->due_date->isPast() => ['Overdue', 'danger'],
            $invoice->payment_status === Invoice::PAYMENT_PARTIAL => ['Parsial', 'info'],
            default => ['Belum Bayar', 'warning'],
        };
    }

    /**
     * "Status Pesanan" - production/order workflow. Cancellation overrides it
     * for the same reason as status() above: the order is not waiting on
     * anything.
     *
     * @return array{string, string}
     */
    private function orderStatus(Invoice $invoice): array
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return ['Dibatalkan', 'danger'];
        }

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
