<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FixInvoiceItemDescriptions extends Command
{
    protected $signature = 'invoices:items:fix-descriptions
        {--apply : Write the corrected descriptions instead of only reporting them}';

    protected $description = 'Repair invoice item descriptions that call a lid or bowl a cup ("Sablon Cup ...")';

    /**
     * Invoice items used to have their description generated as
     * "Sablon Cup {specs}" for every product, so lid and bowl rows were
     * mislabelled and - because the preview labelled rows by description -
     * showed up as another cup. New invoices are fixed at the source
     * (resources/js/support/invoice-item-label.js); this repairs the rows
     * that were already saved with the wrong noun.
     */
    public function handle(): int
    {
        // Soft-deleted items are superseded history kept for the FIFO cost
        // audit trail, so they are deliberately left untouched.
        $candidates = InvoiceItem::query()
            ->with(['product:id,category'])
            ->where('description', 'like', 'Sablon Cup %')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No invoice item descriptions need repairing.');

            return self::SUCCESS;
        }

        $pending = [];
        $unresolved = 0;

        foreach ($candidates as $item) {
            $category = $item->product?->category;

            if ($category === null) {
                // No product left to tell us what this row actually is, so
                // guessing would risk relabelling a genuine cup.
                $unresolved++;

                continue;
            }

            $noun = $this->printedItemNoun($category);

            if ($noun === 'Cup') {
                continue;
            }

            $pending[] = [
                'item' => $item,
                'before' => $item->description,
                'after' => preg_replace('/^Sablon Cup /', "Sablon {$noun} ", $item->description, 1),
            ];
        }

        if ($unresolved > 0) {
            $this->warn("{$unresolved} row(s) skipped: their product record is gone, so the category cannot be confirmed.");
        }

        if ($pending === []) {
            $this->info('No invoice item descriptions need repairing.');

            return self::SUCCESS;
        }

        $this->table(
            ['Invoice item', 'Product', 'Before', 'After'],
            array_map(static fn (array $row): array => [
                $row['item']->getKey(),
                $row['item']->product_name,
                $row['before'],
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
                    $row['item']->forceFill(['description' => $row['after']])->save();
                }
            });
        } catch (Throwable $exception) {
            $this->error("Nothing was written - the update failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("{$count} invoice item description(s) repaired.");

        return self::SUCCESS;
    }

    /**
     * Mirrors printedItemNoun() in resources/js/support/invoice-item-label.js.
     */
    private function printedItemNoun(string $category): string
    {
        return match (true) {
            (bool) preg_match('/tutup|lid/i', $category) => 'Tutup',
            (bool) preg_match('/bowl/i', $category) => 'Bowl',
            default => 'Cup',
        };
    }
}
