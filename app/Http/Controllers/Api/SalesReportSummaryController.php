<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportSummaryRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\SalesReportPeriodPresets;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SalesReportSummaryController extends Controller
{
    /**
     * Return sales report KPI summary for a selected invoice issue-date range.
     */
    public function __invoke(SalesReportSummaryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $range = SalesReportPeriodPresets::resolve($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $dateFrom = $range['from'];
        $dateTo = $range['to'];
        $invoices = $this->invoiceQuery($dateFrom, $dateTo)
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->get();

        $totalSales = (float) $invoices->sum('total_amount');
        $paidAmount = (float) $invoices->sum(fn (Invoice $invoice): float => (float) ($invoice->verified_paid_amount ?? 0));
        $outstandingAmount = max(0, $totalSales - $paidAmount);
        $invoiceCount = $invoices->count();
        $averageInvoiceAmount = $invoiceCount > 0 ? round($totalSales / $invoiceCount, 2) : 0.0;
        $overdueInvoices = $invoices->filter(fn (Invoice $invoice): bool => $this->isOverdue($invoice));
        $overdueAmount = (float) $overdueInvoices->sum(
            fn (Invoice $invoice): float => max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0)),
        );
        $previousTotalSales = $this->previousTotalSales($dateFrom, $dateTo);
        $growthPercentage = $previousTotalSales > 0
            ? round((($totalSales - $previousTotalSales) / $previousTotalSales) * 100, 2)
            : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to' => $dateTo->toDateString(),
                    'label' => $this->formatDate($dateFrom).' - '.$this->formatDate($dateTo),
                ],
                'total_sales' => $totalSales,
                'total_sales_formatted' => $this->formatRupiah($totalSales),
                'invoice_count' => $invoiceCount,
                'paid_invoice_count' => $invoices->where('payment_status', Invoice::PAYMENT_PAID)->count(),
                'outstanding_invoice_count' => $invoices
                    ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIAL])
                    ->count(),
                'paid_amount' => $paidAmount,
                'paid_amount_formatted' => $this->formatRupiah($paidAmount),
                'outstanding_amount' => $outstandingAmount,
                'outstanding_amount_formatted' => $this->formatRupiah($outstandingAmount),
                'overdue_amount' => $overdueAmount,
                'overdue_amount_formatted' => $this->formatRupiah($overdueAmount),
                'average_invoice_amount' => $averageInvoiceAmount,
                'average_invoice_amount_formatted' => $this->formatRupiah($averageInvoiceAmount),
                'comparison' => [
                    'previous_total_sales' => $previousTotalSales,
                    'previous_total_sales_formatted' => $this->formatRupiah($previousTotalSales),
                    'growth_percentage' => $growthPercentage,
                    'growth_label' => $growthPercentage === null
                        ? 'Belum ada data pembanding'
                        : ($growthPercentage >= 0 ? '+' : '').$growthPercentage.'% dari periode sebelumnya',
                ],
                'cards' => $this->summaryCards(
                    $totalSales,
                    $invoiceCount,
                    $averageInvoiceAmount,
                    $outstandingAmount,
                    $growthPercentage,
                ),
                'status_breakdown' => $this->statusBreakdown($invoices),
            ],
        ]);
    }

    private function invoiceQuery(CarbonImmutable $dateFrom, CarbonImmutable $dateTo): Builder
    {
        return Invoice::query()
            ->businessTransaction()
            ->whereBetween('issue_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
    }

    private function previousTotalSales(CarbonImmutable $dateFrom, CarbonImmutable $dateTo): float
    {
        $days = $dateFrom->diffInDays($dateTo) + 1;
        $previousDateTo = $dateFrom->subDay();
        $previousDateFrom = $previousDateTo->subDays($days - 1);

        return (float) $this->invoiceQuery($previousDateFrom, $previousDateTo)->sum('total_amount');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(
        float $totalSales,
        int $invoiceCount,
        float $averageInvoiceAmount,
        float $outstandingAmount,
        ?float $growthPercentage,
    ): array {
        return [
            [
                'key' => 'total_sales',
                'label' => 'Total penjualan',
                'value' => $totalSales,
                'value_formatted' => $this->formatRupiah($totalSales),
                'caption' => $growthPercentage === null
                    ? 'Belum ada data pembanding'
                    : ($growthPercentage >= 0 ? '+' : '').$growthPercentage.'% dari periode sebelumnya',
                'tone' => $growthPercentage !== null && $growthPercentage < 0 ? 'warning' : 'success',
            ],
            [
                'key' => 'invoice_count',
                'label' => 'Invoice aktif',
                'value' => $invoiceCount,
                'value_formatted' => (string) $invoiceCount,
                'caption' => 'Total invoice aktif pada periode ini',
                'tone' => 'brand',
            ],
            [
                'key' => 'average_invoice_amount',
                'label' => 'Rata-rata invoice',
                'value' => $averageInvoiceAmount,
                'value_formatted' => $this->formatRupiah($averageInvoiceAmount),
                'caption' => 'Per transaksi',
                'tone' => 'brand',
            ],
            [
                'key' => 'outstanding_amount',
                'label' => 'Outstanding',
                'value' => $outstandingAmount,
                'value_formatted' => $this->formatRupiah($outstandingAmount),
                'caption' => 'Perlu follow-up pembayaran',
                'tone' => $outstandingAmount > 0 ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    private function statusBreakdown(Collection $invoices): array
    {
        $segments = [
            Invoice::PAYMENT_PAID => ['label' => 'Lunas', 'count' => 0, 'amount' => 0.0],
            Invoice::PAYMENT_PARTIAL => ['label' => 'Parsial', 'count' => 0, 'amount' => 0.0],
            Invoice::PAYMENT_UNPAID => ['label' => 'Menunggu', 'count' => 0, 'amount' => 0.0],
            Invoice::PAYMENT_OVERDUE => ['label' => 'Overdue', 'count' => 0, 'amount' => 0.0],
        ];

        foreach ($invoices as $invoice) {
            $status = $this->isOverdue($invoice) ? Invoice::PAYMENT_OVERDUE : $invoice->payment_status;
            $segments[$status]['count']++;
            $segments[$status]['amount'] += (float) $invoice->total_amount;
        }

        return collect($segments)
            ->map(fn (array $segment, string $status): array => [
                'status' => $status,
                'label' => $segment['label'],
                'count' => $segment['count'],
                'amount' => $segment['amount'],
                'amount_formatted' => $this->formatRupiah($segment['amount']),
            ])
            ->values()
            ->all();
    }

    private function isOverdue(Invoice $invoice): bool
    {
        return $invoice->due_date->isPast()
            && $invoice->payment_status !== Invoice::PAYMENT_PAID;
    }

    private function formatDate(CarbonImmutable $date): string
    {
        return $date->translatedFormat('d M Y');
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
