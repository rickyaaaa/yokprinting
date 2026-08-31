<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoices\GenerateInvoicePdf;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class InvoiceListExportController extends Controller
{
    public function csv(Request $request, ReportCsvExport $export): Response
    {
        $report = $this->report($request);

        return $export->download(
            'daftar-invoice-'.$this->filenameDate($report['date_from'], $report['date_to']).'.csv',
            [
                'Invoice', 'Pelanggan', 'Tanggal', 'Jatuh Tempo', 'Detail Pesanan',
                'Status Pesanan', 'Total', 'Uang Muka', 'Sisa Pembayaran', 'Status Pembayaran',
            ],
            $report['rows']->map(fn (array $row): array => [
                $row['invoice_number'],
                $row['customer'],
                $row['issue_date'],
                $row['due_date'],
                $row['details'],
                $row['order_status'],
                $row['total_amount'],
                $row['paid_amount'],
                $row['remaining_amount'],
                $row['payment_status'],
            ]),
        );
    }

    public function pdf(Request $request): Response
    {
        return $this->renderPdf($request, false, 'Daftar Invoice', 'daftar-invoice');
    }

    public function ordersCsv(Request $request, ReportCsvExport $export): Response
    {
        $report = $this->report($request, true);

        return $export->download(
            'cetak-pesanan-'.$this->filenameDate($report['date_from'], $report['date_to']).'.csv',
            ['Nomor', 'Tanggal', 'Pelanggan', 'Keterangan', 'Status', 'Total', 'Uang Muka'],
            $report['rows']->map(fn (array $row): array => [
                $row['invoice_number'],
                $row['issue_date'],
                $row['customer'],
                $row['details'],
                $row['order_status'],
                $row['total_amount'],
                $row['paid_amount'],
            ]),
        );
    }

    public function ordersPdf(Request $request, GenerateInvoicePdf $generateInvoicePdf): Response
    {
        $report = $this->report($request, true);
        $pdf = $generateInvoicePdf->generateSalesOrderBatch(
            $report['invoices'],
            $report['date_from'],
            $report['date_to'],
        );

        return response($pdf->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename.'"',
            'Content-Length' => (string) strlen($pdf->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function renderPdf(Request $request, bool $defaultToday, string $title, string $filename): Response
    {
        $report = $this->report($request, $defaultToday);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.invoice-list', [
            'title' => $title,
            'rows' => $report['rows'],
            'dateFrom' => $report['date_from'],
            'dateTo' => $report['date_to'],
        ])->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'-'.$this->filenameDate($report['date_from'], $report['date_to']).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array{invoices: Collection<int, Invoice>, rows: Collection<int, array<string, mixed>>, date_from: ?string, date_to: ?string}
     */
    private function report(Request $request, bool $defaultToday = false): array
    {
        $validated = $request->validate([
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                'all', Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_CANCELLED,
                Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIAL, Invoice::PAYMENT_PAID, 'overdue',
            ])],
        ]);

        $today = CarbonImmutable::now((string) config('app.timezone', 'UTC'))->toDateString();
        $dateFrom = $validated['date_from'] ?? ($defaultToday ? $today : null);
        $dateTo = $validated['date_to'] ?? ($defaultToday ? $today : null);

        $invoices = Invoice::query()
            ->with([
                'customer',
                'items',
                'payments' => fn ($query) => $query->where('status', Payment::STATUS_VERIFIED)->orderBy('payment_date'),
            ])
            ->when($dateFrom, fn ($query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->when($validated['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when(($validated['status'] ?? null) && ($validated['status'] ?? null) !== 'all', function ($query) use ($validated): void {
                $status = $validated['status'];

                if ($status === 'overdue') {
                    $query->whereDate('due_date', '<', today())->where('payment_status', '!=', Invoice::PAYMENT_PAID);
                } elseif (in_array($status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_CANCELLED], true)) {
                    $query->where('status', $status);
                } else {
                    $query->where('payment_status', $status);
                }
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        return [
            'invoices' => $invoices,
            'rows' => $invoices->map(fn (Invoice $invoice): array => $this->formatInvoice($invoice))->values(),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /** @return array<string, mixed> */
    private function formatInvoice(Invoice $invoice): array
    {
        $paidAmount = (float) $invoice->payments->sum('amount');
        $totalAmount = (float) $invoice->total_amount;
        $remainingAmount = max(0, $totalAmount - $paidAmount);

        return [
            'invoice_number' => $invoice->invoice_number,
            'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
            'issue_date' => $invoice->issue_date->toDateString(),
            'issue_date_label' => $invoice->issue_date->locale('id')->translatedFormat('d M Y'),
            'due_date' => $invoice->due_date->toDateString(),
            'due_date_label' => $invoice->due_date->locale('id')->translatedFormat('d M Y'),
            'details' => $this->details($invoice),
            'order_status' => $this->orderStatusLabel($invoice),
            'total_amount' => $totalAmount,
            'total_amount_label' => $this->rupiah($totalAmount),
            'paid_amount' => $paidAmount,
            'paid_amount_label' => $this->rupiah($paidAmount),
            'remaining_amount' => $remainingAmount,
            'remaining_amount_label' => $this->rupiah($remainingAmount),
            'payment_status' => $this->statusLabel($invoice, $paidAmount),
        ];
    }

    private function details(Invoice $invoice): string
    {
        $items = $invoice->items->map(function ($item): string {
            $quantity = rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',');
            $specification = collect([
                $item->description,
                $item->cup_size,
                $item->cup_model,
                $item->grammage,
                $item->jenis_cetak,
                $item->screen_printing_color ? "Tinta {$item->screen_printing_color}" : null,
            ])->filter()->unique()->implode(' | ');

            return collect([
                "{$item->product_name} x {$quantity} {$item->unit}",
                $specification,
            ])->filter()->implode(' - ');
        })->filter()->implode("\n");

        $notes = collect([
            $invoice->notes ? "Catatan: {$invoice->notes}" : null,
            $invoice->terms ? "Termin: {$invoice->terms}" : null,
        ])->filter()->implode("\n");

        return collect([$items, $notes])->filter()->implode("\n") ?: '-';
    }

    /**
     * Mirrors InvoiceIndexPageController::orderStatus() - a cancelled invoice
     * is not waiting on production, so its stale workflow status must not be
     * exported as if it were.
     */
    private function orderStatusLabel(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'Dibatalkan';
        }

        if ($invoice->order_process_status === Invoice::ORDER_PROCESS_DRAFT
            && $invoice->production_status !== Invoice::PRODUCTION_DRAFT) {
            return match ($invoice->production_status) {
                Invoice::PRODUCTION_IN_PRODUCTION => 'Masih produksi',
                Invoice::PRODUCTION_COMPLETED => 'Selesai',
                Invoice::PRODUCTION_AWAITING_DP => 'Menunggu DP',
                Invoice::PRODUCTION_DESIGN_ACC => 'ACC Mockup/Desain',
                Invoice::PRODUCTION_READY_FOR_PICKUP => 'Siap Diambil/Kirim',
                default => 'Drafting',
            };
        }

        return match ($invoice->order_process_status) {
            Invoice::ORDER_PROCESS_IN_PRODUCTION => 'Masih produksi',
            Invoice::ORDER_PROCESS_READY_TO_SHIP => 'Siap dikirim',
            Invoice::ORDER_PROCESS_COMPLETED => 'Selesai',
            default => 'Menunggu diproses',
        };
    }

    /**
     * Mirrors InvoiceIndexPageController::status(). Cancellation overrides the
     * payment status; draft does NOT - a draft invoice is a real transaction
     * with a real payment status (see Invoice::scopeBusinessTransaction()), and
     * showing "Draft" here hid whether it was actually paid.
     */
    private function statusLabel(Invoice $invoice, float $paidAmount): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'Dibatalkan';
        }

        if ($paidAmount >= (float) $invoice->total_amount) {
            return 'Lunas';
        }

        if ($invoice->due_date->isPast()) {
            return 'Overdue';
        }

        return match ($invoice->payment_status) {
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            default => 'Menunggu',
        };
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function filenameDate(?string $dateFrom, ?string $dateTo): string
    {
        return $dateFrom && $dateTo && $dateFrom === $dateTo
            ? $dateFrom
            : (($dateFrom ?: 'semua').'-'.($dateTo ?: 'tanggal'));
    }
}
