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
        $year = ($date ?? CarbonImmutable::now())->year;

        return DB::transaction(function () use ($year): string {
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
                '%s-%d-%0'.self::SEQUENCE_DIGITS.'d',
                self::PREFIX,
                $year,
                $nextNumber,
            );
        });
    }

    private function latestPersistedSequence(int $year): int
    {
        $pattern = sprintf('%s-%d-%%', self::PREFIX, $year);
        $exactPattern = sprintf(
            '/^%s-%d-(\d+)$/',
            preg_quote(self::PREFIX, '/'),
            $year,
        );

        return Invoice::withTrashed()
            ->where('invoice_number', 'like', $pattern)
            ->pluck('invoice_number')
            ->map(function (string $invoiceNumber) use ($exactPattern): int {
                return preg_match($exactPattern, $invoiceNumber, $matches) === 1
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() ?? 0;
    }
}
