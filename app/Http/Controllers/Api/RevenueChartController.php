<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueChartController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), ['monthly', 'quarterly', 'yearly'], true)
            ? $request->query('period')
            : 'monthly';
        $buckets = $this->buckets($period, CarbonImmutable::now(config('app.timezone')));
        $start = $buckets[0]['start'];
        $end = $buckets[array_key_last($buckets)]['end'];

        $invoices = Invoice::query()
            ->finalized()
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->get();
        $payments = Payment::query()
            ->verified()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $issued = collect($buckets)->map(fn (array $bucket): float => (float) $invoices
            ->filter(fn (Invoice $invoice): bool => $invoice->issue_date->betweenIncluded($bucket['start'], $bucket['end']))
            ->sum('total_amount'));
        $paid = collect($buckets)->map(fn (array $bucket): float => (float) $payments
            ->filter(fn (Payment $payment): bool => $payment->payment_date->betweenIncluded($bucket['start'], $bucket['end']))
            ->sum('amount'));

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'label' => match ($period) {
                    'quarterly' => '4 kuartal terakhir',
                    'yearly' => '3 tahun terakhir',
                    default => '6 bulan terakhir',
                },
                'headline' => $this->rupiah((float) $issued->last()),
                'caption' => 'Dihitung dari invoice final dan pembayaran terverifikasi.',
                'labels' => collect($buckets)->pluck('label')->all(),
                'issued' => $issued->all(),
                'paid' => $paid->all(),
            ],
        ]);
    }

    /** @return list<array{start: CarbonImmutable, end: CarbonImmutable, label: string}> */
    private function buckets(string $period, CarbonImmutable $today): array
    {
        $count = $period === 'yearly' ? 3 : ($period === 'quarterly' ? 4 : 6);

        return collect(range($count - 1, 0))
            ->map(function (int $offset) use ($period, $today): array {
                if ($period === 'yearly') {
                    $start = $today->subYears($offset)->startOfYear();
                    $end = $start->endOfYear();
                    $label = $start->format('Y');
                } elseif ($period === 'quarterly') {
                    $start = $today->startOfQuarter()->subQuarters($offset);
                    $end = $start->endOfQuarter();
                    $label = 'Q'.$start->quarter.' '.$start->year;
                } else {
                    $start = $today->startOfMonth()->subMonths($offset);
                    $end = $start->endOfMonth();
                    $label = $start->locale('id')->translatedFormat('M');
                }

                return compact('start', 'end', 'label');
            })
            ->values()
            ->all();
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
