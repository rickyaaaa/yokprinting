<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListGrossProfitReportRequest;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GrossProfitReportController extends Controller
{
    public function index(ListGrossProfitReportRequest $request): JsonResponse
    {
        $rows = $this->rows($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'invoice_count' => $rows->count(),
                    'revenue' => round((float) $rows->sum('product_revenue'), 2),
                    'total_hpp' => round((float) $rows->sum('total_hpp'), 2),
                    'company_shipping' => round((float) $rows->sum('company_shipping_cost'), 2),
                    'gross_profit' => round((float) $rows->sum('gross_profit'), 2),
                ],
                'invoices' => $rows->values(),
            ],
        ]);
    }

    public function export(ListGrossProfitReportRequest $request, ReportCsvExport $export): Response
    {
        $rows = $this->rows($request->validated())
            ->map(fn (array $row): array => [
                $row['invoice_number'],
                $row['issue_date'],
                $row['customer'],
                $row['payment_status'],
                $row['product_revenue'],
                $row['total_hpp'],
                $row['company_shipping_cost'],
                $row['gross_profit'],
            ]);

        return $export->download(
            'laporan-laba-kotor-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Invoice', 'Tanggal', 'Customer', 'Status', 'Revenue Produk', 'Total HPP', 'Ongkir Perusahaan', 'Laba Kotor'],
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rows(array $filters)
    {
        $dateFrom = $filters['date_from'] ?? CarbonImmutable::now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? CarbonImmutable::now()->toDateString();
        $status = $filters['status'] ?? 'all';

        return Invoice::query()
            ->with('customer')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereBetween('issue_date', [$dateFrom, $dateTo])
            ->when($status !== 'all', fn ($query) => $query->where('payment_status', $status))
            ->orderBy('issue_date')
            ->orderBy('invoice_number')
            ->get()
            ->map(function (Invoice $invoice): array {
                $productRevenue = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
                $companyShipping = $invoice->shipping_type === Invoice::SHIPPING_COMPANY_FREE_SHIPPING
                    ? (float) $invoice->shipping_cost
                    : 0.0;

                return [
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date' => $invoice->issue_date->toDateString(),
                    'customer' => $invoice->customer?->name,
                    'payment_status' => $invoice->payment_status,
                    'product_revenue' => $productRevenue,
                    'total_hpp' => (float) $invoice->total_hpp,
                    'company_shipping_cost' => $companyShipping,
                    'gross_profit' => (float) $invoice->gross_profit,
                    'gross_margin_percent' => $productRevenue > 0
                        ? round(((float) $invoice->gross_profit / $productRevenue) * 100, 2)
                        : 0.0,
                ];
            });
    }
}
