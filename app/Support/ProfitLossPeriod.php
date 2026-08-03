<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ProfitLossPeriod
{
    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const CUSTOM = 'custom';

    /**
     * @return array<string, array{date_from: string, date_to: string}>
     */
    public static function presets(): array
    {
        $today = SalesReportPeriodPresets::today();

        return [
            self::DAILY => [
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            ...SalesReportPeriodPresets::forDate($today),
        ];
    }

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [self::DAILY, self::WEEKLY, self::MONTHLY, self::YEARLY, self::CUSTOM];
    }

    /**
     * @return array{key: string, date_from: string, date_to: string, label: string}
     */
    public static function resolve(string $period = self::MONTHLY, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $timezone = (string) config('app.timezone', 'UTC');

        if ($period === self::CUSTOM) {
            if ($dateFrom === null || $dateTo === null) {
                throw ValidationException::withMessages([
                    'date_from' => ['Tanggal awal wajib diisi untuk rentang kustom.'],
                    'date_to' => ['Tanggal akhir wajib diisi untuk rentang kustom.'],
                ]);
            }

            $from = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay();
            $to = CarbonImmutable::parse($dateTo, $timezone)->startOfDay();
        } else {
            $preset = self::presets()[$period] ?? self::presets()[self::MONTHLY];
            $from = CarbonImmutable::parse($preset['date_from'], $timezone)->startOfDay();
            $to = CarbonImmutable::parse($preset['date_to'], $timezone)->startOfDay();
        }

        return [
            'key' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'label' => $from->isSameDay($to)
                ? $from->locale('id')->translatedFormat('d F Y')
                : $from->locale('id')->translatedFormat('d M Y').' - '.$to->locale('id')->translatedFormat('d M Y'),
        ];
    }
}
