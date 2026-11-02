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

    private const SEQUENCE_TABLE = 'invoice_monthly_number_sequences';

    /**
     * Generate the next invoice number for the month of the given issue date,
     * e.g. INV-2026-11-0001. The sequence resets every month and is safe under
     * concurrency: the (year, month) sequence row is row-locked inside a
     * transaction. Numbers already taken by existing invoices (including
     * soft-deleted ones and those issued under the old yearly sequence) are
     * skipped rather than reused.
     */
    public function generate(?CarbonInterface $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $year = $date->year;
        $month = $date->month;

        return DB::transaction(function () use ($year, $month): string {
            $now = now();

            // Upsert rather than insertOrIgnore: on MySQL a duplicate-key
            // ignore takes a shared lock, and several requests upgrading
            // their shared locks at the start of a month deadlock. The
            // ON DUPLICATE KEY UPDATE path takes an exclusive lock instead.
            DB::table(self::SEQUENCE_TABLE)->upsert([
                'year' => $year,
                'month' => $month,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['year', 'month'], ['updated_at']);

            $sequence = DB::table(self::SEQUENCE_TABLE)
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            $usedNumbers = $this->persistedInvoiceNumbers($year, $month);
            $nextNumber = (int) $sequence->last_number;

            do {
                $nextNumber++;
                $invoiceNumber = $this->format($year, $month, $nextNumber);
            } while (isset($usedNumbers[$invoiceNumber]));

            DB::table(self::SEQUENCE_TABLE)
                ->where('year', $year)
                ->where('month', $month)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            return $invoiceNumber;
        });
    }

    private function format(int $year, int $month, int $number): string
    {
        return sprintf(
            '%s-%d-%02d-%0'.self::SEQUENCE_DIGITS.'d',
            self::PREFIX,
            $year,
            $month,
            $number,
        );
    }

    /**
     * @return array<string, int> invoice numbers of the month, keyed for lookup
     */
    private function persistedInvoiceNumbers(int $year, int $month): array
    {
        return Invoice::withTrashed()
            ->where('invoice_number', 'like', sprintf('%s-%d-%02d-%%', self::PREFIX, $year, $month))
            ->pluck('invoice_number')
            ->flip()
            ->all();
    }
}
