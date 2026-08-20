<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListCustomerSalesProfitReportRequest;
use App\Models\Invoice;
use App\Services\Reports\GeneratedReportFile;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CustomerSalesProfitReportController extends Controller
{
    public function index(ListCustomerSalesProfitReportRequest $request): JsonResponse
    {
        $report = $this->report($request->validated());

        return response()->json(['status' => 'success', 'data' => $report]);
    }

    public function export(ListCustomerSalesProfitReportRequest $request, ReportCsvExport $export): Response
    {
        $report = $this->report($request->validated());
        $rows = collect($report['customers'])
            ->flatMap(fn (array $customer): Collection => collect($customer['invoices'])->map(fn (array $row): array => [
                $customer['customer'],
                $row['issue_date'],
                $row['invoice_number'],
                $row['transaction_type'],
                $row['sales'],
                $row['fifo_hpp'],
                $row['gross_profit'],
                $row['margin_percent'],
            ]));

        return $export->download(
            "penjualan-per-pelanggan-{$report['period']['date_from']}-sampai-{$report['period']['date_to']}.csv",
            ['Customer', 'Tanggal', 'Invoice', 'Tipe Transaksi', 'Penjualan', 'HPP FIFO', 'Laba Kotor', 'Margin %'],
            $rows,
        );
    }

    public function pdf(ListCustomerSalesProfitReportRequest $request): Response
    {
        $report = $this->report($request->validated());
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.customer-sales', compact('report'))->render(), 'UTF-8');
        $dompdf->render();

        return $this->download(new GeneratedReportFile(
            $dompdf->output(),
            "penjualan-per-pelanggan-{$report['period']['date_from']}-sampai-{$report['period']['date_to']}.pdf",
            'application/pdf',
        ));
    }

    /** @param array<string, mixed> $filters */
    private function report(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'] ?? now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'] ?? now())->endOfDay();
        $status = $filters['status'] ?? 'all';

        $invoices = Invoice::query()
            ->with('customer')
            ->finalized()
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($status !== 'all', fn ($query) => $query->where('payment_status', $status))
            ->orderBy('customer_id')
            ->orderBy('issue_date')
            ->orderBy('invoice_number')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice): array {
            $sales = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
            $hpp = round((float) $invoice->total_hpp, 2);
            $profit = round($sales - $hpp, 2);

            return [
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date->toDateString(),
                'transaction_type' => 'Penjualan',
                'sales' => $sales,
                'fifo_hpp' => $hpp,
                'gross_profit' => $profit,
                'margin_percent' => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
                'payment_status' => $invoice->payment_status,
                'customer_id' => $invoice->customer_id,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
            ];
        });

        $customers = $rows->groupBy('customer_id')->map(function (Collection $customerRows): array {
            $sales = round((float) $customerRows->sum('sales'), 2);
            $hpp = round((float) $customerRows->sum('fifo_hpp'), 2);
            $profit = round($sales - $hpp, 2);

            return [
                'customer_id' => $customerRows->first()['customer_id'],
                'customer' => $customerRows->first()['customer'],
                'invoices' => $customerRows->values()->all(),
                'total_sales' => $sales,
                'total_hpp' => $hpp,
                'gross_profit' => $profit,
                'margin_percent' => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
            ];
        })->values();

        return [
            'period' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'summary' => [
                'customer_count' => $customers->count(),
                'invoice_count' => $rows->count(),
                'sales' => round((float) $rows->sum('sales'), 2),
                'fifo_hpp' => round((float) $rows->sum('fifo_hpp'), 2),
                'gross_profit' => round((float) $rows->sum('gross_profit'), 2),
                'margin_percent' => $rows->sum('sales') > 0
                    ? round(((float) $rows->sum('gross_profit') / (float) $rows->sum('sales')) * 100, 2)
                    : 0.0,
            ],
            'customers' => $customers->all(),
        ];
    }

    private function download(GeneratedReportFile $file): Response
    {
        return response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => "attachment; filename=\"{$file->filename}\"",
            'Content-Length' => (string) strlen($file->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
