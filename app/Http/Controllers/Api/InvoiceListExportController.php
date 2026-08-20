<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceListExportController extends Controller
{
    public function csv(Request $request, ReportCsvExport $export): Response
    {
        $invoices = $this->query($request)->get();
        $rows = $invoices->map(fn (Invoice $invoice): array => [
            $invoice->invoice_number,
            $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
            $invoice->issue_date->toDateString(),
            $invoice->due_date->toDateString(),
            (float) $invoice->total_amount,
            $this->statusLabel($invoice),
        ]);

        return $export->download(
            'daftar-invoice-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Invoice', 'Customer', 'Tanggal', 'Jatuh Tempo', 'Total', 'Status'],
            $rows,
        );
    }

    public function pdf(Request $request): Response
    {
        $invoices = $this->query($request)->get();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.invoice-list', [
            'invoices' => $invoices,
        ])->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="daftar-invoice-'.now()->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function query(Request $request)
    {
        return Invoice::query()
            ->with('customer')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->date_to))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request): void {
                if ($request->status === 'overdue') {
                    $query->whereDate('due_date', '<', today())->where('payment_status', '!=', Invoice::PAYMENT_PAID);
                } elseif (in_array($request->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_CANCELLED], true)) {
                    $query->where('status', $request->status);
                } else {
                    $query->where('payment_status', $request->status);
                }
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    private function statusLabel(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_DRAFT) return 'Draft';
        if ($invoice->status === Invoice::STATUS_CANCELLED) return 'Dibatalkan';
        if ($invoice->due_date->isPast() && $invoice->payment_status !== Invoice::PAYMENT_PAID) return 'Overdue';

        return match ($invoice->payment_status) {
            Invoice::PAYMENT_PAID => 'Lunas',
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            default => 'Menunggu',
        };
    }
}
