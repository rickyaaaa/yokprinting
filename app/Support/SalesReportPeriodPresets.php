<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SalesReportPeriodPresets
{
    /**
     * Build the current report periods in the configured application timezone.
     *
     * @return array<string, array{date_from: string, date_to: string}>
     */
    public static function current(): array
    {
        return self::forDate(self::today());
    }

    /**
     * Return today's date in the configured application timezone.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('app.timezone', 'UTC'))->startOfDay();
    }

    /**
     * Build report periods anchored to the supplied local date.
     *
     * @return array<string, array{date_from: string, date_to: string}>
     */
    public static function forDate(CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $weekStartsAt = $today->startOfWeek(CarbonInterface::MONDAY);

        return [
            'weekly' => [
                'date_from' => $weekStartsAt->toDateString(),
                'date_to' => $weekStartsAt->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            ],
            'monthly' => [
                'date_from' => $today->startOfMonth()->toDateString(),
                'date_to' => $today->endOfMonth()->toDateString(),
            ],
            'yearly' => [
                'date_from' => $today->startOfYear()->toDateString(),
                'date_to' => $today->endOfYear()->toDateString(),
            ],
        ];
    }

    /**
     * Resolve an optional report range, defaulting to the current monthly preset.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function resolve(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $monthly = self::current()['monthly'];
        $timezone = (string) config('app.timezone', 'UTC');

        return [
            'from' => CarbonImmutable::parse($dateFrom ?? $monthly['date_from'], $timezone)->startOfDay(),
            'to' => CarbonImmutable::parse($dateTo ?? $monthly['date_to'], $timezone)->endOfDay(),
        ];
    }
}
