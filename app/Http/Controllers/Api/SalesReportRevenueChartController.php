<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRevenueChartRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\SalesReportPeriodPresets;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        $today = SalesReportPeriodPresets::today();
        $buckets = $this->buckets($period, $today);
        $dateFrom = $buckets->first()['from'];
        $dateTo = $buckets->last()['to'];
        $invoices = Invoice::query()
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->businessTransaction()
            ->where('issue_date', '>=', $dateFrom->toDateString())
            ->where('issue_date', '<', $dateTo->addDay()->toDateString())
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
                'label' => $this->periodLabel($period, $today),
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
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
    private function buckets(string $period, CarbonImmutable $today): Collection
    {
        return match ($period) {
            'weekly' => $this->weeklyBuckets($today),
            'yearly' => $this->yearlyBuckets($today),
            default => $this->monthlyBuckets($today),
        };
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function weeklyBuckets(CarbonImmutable $today): Collection
    {
        $start = $today->startOfWeek(CarbonInterface::MONDAY);

        return collect(range(0, 6))->map(function (int $offset) use ($start): array {
            $day = $start->addDays($offset);

            return [
                'label' => $this->dayLabel($day),
                'from' => $day->startOfDay(),
                'to' => $day->endOfDay(),
            ];
        });
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function monthlyBuckets(CarbonImmutable $today): Collection
    {
        $start = $today->startOfMonth();

        return collect(range(0, $today->daysInMonth - 1))->map(function (int $offset) use ($start): array {
            $day = $start->addDays($offset);

            return [
                'label' => (string) $day->day,
                'from' => $day->startOfDay(),
                'to' => $day->endOfDay(),
            ];
        });
    }

    /**
     * @return Collection<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}
     */
    private function yearlyBuckets(CarbonImmutable $today): Collection
    {
        $start = $today->startOfYear();

        return collect(range(0, 11))->map(function (int $offset) use ($start): array {
            $month = $start->addMonths($offset);

            return [
                'label' => $this->monthLabel($month),
                'from' => $month->startOfMonth(),
                'to' => $month->endOfMonth(),
            ];
        });
    }

    private function invoiceBelongsToBucket(Invoice $invoice, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return CarbonImmutable::parse($invoice->issue_date)->betweenIncluded($from, $to);
    }

    private function periodLabel(string $period, CarbonImmutable $today): string
    {
        $presets = SalesReportPeriodPresets::forDate($today);

        return match ($period) {
            'weekly' => sprintf('%s - %s', $presets['weekly']['date_from'], $presets['weekly']['date_to']),
            'yearly' => (string) $today->year,
            default => $this->monthLabel($today).' '.$today->year,
        };
    }

    private function dayLabel(CarbonImmutable $date): string
    {
        $days = [
            0 => 'Min',
            1 => 'Sen',
            2 => 'Sel',
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum',
            6 => 'Sab',
        ];

        return $days[$date->dayOfWeek];
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
