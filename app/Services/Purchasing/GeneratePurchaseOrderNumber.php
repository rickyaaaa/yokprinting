<?php

namespace App\Services\Purchasing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrderNumber
{
    private const PREFIX = 'PO';

    private const SEQUENCE_DIGITS = 4;

    /**
     * Generate the next PO number for the given month, e.g. PO-202608-0001.
     * Safe under concurrency: the sequence row is row-locked inside a
     * transaction, mirroring GenerateInvoiceNumber.
     */
    public function generate(?CarbonInterface $date = null): string
    {
        $period = ($date ?? CarbonImmutable::now())->format('Ym');

        return DB::transaction(function () use ($period): string {
            $now = now();

            DB::table('purchase_order_number_sequences')->insertOrIgnore([
                'period' => $period,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('purchase_order_number_sequences')
                ->where('period', $period)
                ->lockForUpdate()
                ->first();
            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('purchase_order_number_sequences')
                ->where('period', $period)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            return sprintf(
                '%s-%s-%0'.self::SEQUENCE_DIGITS.'d',
                self::PREFIX,
                $period,
                $nextNumber,
            );
        });
    }
}
