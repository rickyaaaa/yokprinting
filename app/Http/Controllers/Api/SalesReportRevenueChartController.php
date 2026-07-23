<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRevenueChartRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SalesReportRevenueChartController extends Controller
{
    /**
     * Return revenue trend chart data for the sales report page.
     */
    public function __invoke(SalesReportRevenueChartRequest $request): JsonResponse
    {
        $period = $request->validated('period', 'monthly');
        $buckets = $this->buckets($period);
        $dateFrom = $buckets->first()['from'];
        $dateTo = $buckets->last()['to'];
        $invoices = Invoice::query()
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereBetween('issue_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get();

        $revenue = [];
        $paid = [];
        $target = [];

        foreach ($buckets as $bucket) {
            $bucketInvoices = $invoices->filter(
                fn (Invoice $invoice): bool => $this->invoiceBelongsToBucket($invoice, $bucket['from'], $bucket['to']),
            );
            $bucketRevenue = (float) $bucketInvoices->sum('total_amount');
            $bucketPaid = (float) $bucketInvoices->sum(
                fn (Invoice $invoice): float => (float) ($invoice->verified_paid_amount ?? 0),
            );

            $revenue[] = $bucketRevenue;
            $paid[] = $bucketPaid;
            $target[] = round($bucketRevenue * 1.1, 2);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'label' => $this->periodLabel($period),
                'target_source' => 'computed_110_percent_of_revenue',
                'labels' => $buckets->pluck('label')->all(),
                'revenue' => $revenue,
                'paid' => $paid,
                'target' => $target,
                'datasets' => [
                    [
                        'key' => 'revenue',
                        'label' => 'Pendapatan',
                        'data' => $revenue,
                    ],
                    [
                        'key' => 'target',
                        'label' => 'Target',
                        'data' => $target,
                    ],
                    [
                        'key' => 'paid',
                        'label' => 'Tertagih',
                        'data' => $paid,
                    ],
                ],
                'totals' => [
                    'revenue' => array_sum($revenue),
                    'revenue_formatted' => $this->formatRupiah(array_sum($revenue)),
                    'paid' => array_sum($paid),
                    'paid_formatted' => $this->formatRupiah(array_sum($paid)),
                    'target' => array_sum($target),
                    'target_formatted' => $this->formatRupiah(array_sum($target)),
                ],
            ],
        ]);
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function buckets(string $period): Collection
    {
        return match ($period) {
            'quarterly' => $this->quarterlyBuckets(),
            'yearly' => $this->yearlyBuckets(),
            default => $this->monthlyBuckets(),
        };
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function monthlyBuckets(): Collection
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);

        return collect(range(0, 5))->map(function (int $offset) use ($start): array {
            $month = $start->addMonths($offset);

            return [
                'label' => $this->monthLabel($month),
                'from' => $month->startOfMonth(),
                'to' => $month->endOfMonth(),
            ];
        });
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function quarterlyBuckets(): Collection
    {
        $start = CarbonImmutable::now()->firstOfQuarter()->subQuarters(3);

        return collect(range(0, 3))->map(function (int $offset) use ($start): array {
            $quarter = $start->addQuarters($offset);

            return [
                'label' => 'Q'.$quarter->quarter.' '.$quarter->year,
                'from' => $quarter->firstOfQuarter(),
                'to' => $quarter->lastOfQuarter(),
            ];
        });
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}
     */
    private function yearlyBuckets(): Collection
    {
        $start = CarbonImmutable::now()->startOfYear()->subYears(2);

        return collect(range(0, 2))->map(function (int $offset) use ($start): array {
            $year = $start->addYears($offset);

            return [
                'label' => (string) $year->year,
                'from' => $year->startOfYear(),
                'to' => $year->endOfYear(),
            ];
        });
    }

    private function invoiceBelongsToBucket(Invoice $invoice, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $invoice->issue_date->betweenIncluded($from, $to);
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'quarterly' => '4 kuartal terakhir',
            'yearly' => '3 tahun terakhir',
            default => '6 bulan terakhir',
        };
    }

    private function monthLabel(CarbonImmutable $date): string
    {
        $months = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return $months[$date->month];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
