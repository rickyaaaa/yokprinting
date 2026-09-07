<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FixInvoiceItemProductNames extends Command
{
    protected $signature = 'invoices:items:fix-product-names
        {--apply : Write the recovered product names instead of only reporting them}';

    protected $description = 'Repair invoice items saved with the generated "Sablon ..." description in place of the product name';

    /**
     * Invoices saved before the preview stopped labelling a line by its
     * description kept that description in product_name, so the stored
     * invoice PDF - which prints the column as-is - shows "Sablon Cup 12 Oz
     * Datar ..." where the product should be.
     *
     * The real name is recoverable because the row still points at its
     * product. Rows whose product is gone are skipped rather than guessed at,
     * and nothing outside that pattern is touched, so re-running is safe.
     */
    public function handle(): int
    {
        $candidates = InvoiceItem::query()
            ->with(['product:id,name'])
            ->where('product_name', 'like', 'Sablon %')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No invoice item product names need repairing.');

            return self::SUCCESS;
        }

        $pending = [];
        $unresolved = 0;

        foreach ($candidates as $item) {
            $name = $item->product?->name;

            if ($name === null || $name === $item->product_name) {
                $unresolved++;

                continue;
            }

            $pending[] = ['item' => $item, 'before' => $item->product_name, 'after' => $name];
        }

        if ($unresolved > 0) {
            $this->warn("{$unresolved} row(s) skipped: no product left to recover the name from.");
        }

        if ($pending === []) {
            $this->info('No invoice item product names need repairing.');

            return self::SUCCESS;
        }

        $this->table(
            ['Item', 'Invoice', 'Before', 'After'],
            array_map(static fn (array $row): array => [
                $row['item']->getKey(),
                $row['item']->invoice?->invoice_number ?? '-',
                str($row['before'])->limit(45)->toString(),
                $row['after'],
            ], $pending),
        );

        $count = count($pending);

        if (! $this->option('apply')) {
            $this->info("{$count} row(s) would be updated. Re-run with --apply to write the changes.");

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($pending): void {
                foreach ($pending as $row) {
                    $row['item']->forceFill(['product_name' => $row['after']])->save();
                }
            });
        } catch (Throwable $exception) {
            $this->error("Nothing was written - the update failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("{$count} invoice item product name(s) repaired.");

        return self::SUCCESS;
    }
}
