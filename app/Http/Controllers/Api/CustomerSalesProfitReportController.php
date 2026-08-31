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
        $keyword = trim((string) ($filters['q'] ?? ''));

        $invoices = Invoice::query()
            ->with('customer')
            ->businessTransaction()
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($status !== 'all', fn ($query) => $query->where('payment_status', $status))
            // Client request: find one invoice by number without knowing which
            // customer card it sits under. Customer name is matched too so the
            // single box works either way.
            ->when($keyword !== '', fn ($query) => $query->where(function ($query) use ($keyword): void {
                $query
                    ->where('invoice_number', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$keyword}%"));
            }))
            // Groups the rows per customer for groupBy() below; the cards
            // themselves are ordered by total sales afterwards. Within a
            // customer: newest invoice first, invoice_number as a
            // deterministic tiebreak on the same date.
            ->orderBy('customer_id')
            ->orderByDesc('issue_date')
            ->orderByDesc('invoice_number')
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
        })
            // Biggest customer first - the question this report answers. The
            // cards used to come out in customer_id (creation) order, which
            // reads as random. Name is a stable tiebreak so equal totals never
            // shuffle between requests.
            ->sortBy([
                ['total_sales', 'desc'],
                ['customer', 'asc'],
            ])
            ->values();

        $totalSales = round((float) $rows->sum('sales'), 2);
        $totalHpp = round((float) $rows->sum('fifo_hpp'), 2);
        // Derived the same way as each customer subtotal (sales - hpp) rather
        // than by summing already-rounded per-row profits, so the grand total
        // always equals the sum of the subtotals shown underneath it.
        $totalProfit = round($totalSales - $totalHpp, 2);

        return [
            'period' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'summary' => [
                'customer_count' => $customers->count(),
                'invoice_count' => $rows->count(),
                'sales' => $totalSales,
                'fifo_hpp' => $totalHpp,
                'gross_profit' => $totalProfit,
                'margin_percent' => $totalSales > 0
                    ? round(($totalProfit / $totalSales) * 100, 2)
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
