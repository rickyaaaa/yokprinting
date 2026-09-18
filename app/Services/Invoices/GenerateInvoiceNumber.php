<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceNumber
{
    private const PREFIX = 'INV';

    private const SEQUENCE_DIGITS = 4;

    public function generate(?CarbonInterface $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $year = $date->year;
        $month = $date->month;

        return DB::transaction(function () use ($year, $month): string {
            $now = now();

            DB::table('invoice_number_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => $this->latestPersistedSequence($year),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('invoice_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('invoice_number_sequences')
                ->where('year', $year)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            return sprintf(
                '%s-%d-%02d-%0'.self::SEQUENCE_DIGITS.'d',
                self::PREFIX,
                $year,
                $month,
                $nextNumber,
            );
        });
    }

    private function latestPersistedSequence(int $year): int
    {
        $pattern = sprintf('%s-%d-%%', self::PREFIX, $year);
        $prefix = preg_quote(self::PREFIX, '/');
        $exactPatterns = [
            sprintf('/^%s-%d-(\d+)$/', $prefix, $year),
            sprintf('/^%s-%d-\d{2}-(\d+)$/', $prefix, $year),
        ];

        return Invoice::withTrashed()
            ->where('invoice_number', 'like', $pattern)
            ->pluck('invoice_number')
            ->map(function (string $invoiceNumber) use ($exactPatterns): int {
                foreach ($exactPatterns as $pattern) {
                    if (preg_match($pattern, $invoiceNumber, $matches) === 1) {
                        return (int) $matches[1];
                    }
                }

                return 0;
            })
            ->max() ?? 0;
    }
}
