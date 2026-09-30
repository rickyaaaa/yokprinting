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
            ->with(['product:id,name,category,cup_size,cup_model,grammage,screen_printing_color,sides'])
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
                $corrected = $this->correctedCupDescription($item);

                if ($corrected !== null && $corrected !== $item->description) {
                    $pending[] = [
                        'item' => $item,
                        'before' => $item->description,
                        'after' => $corrected,
                        'reason' => 'Ukuran/spec invoice berbeda dari produk yang terhubung.',
                    ];
                }

                continue;
            }

            $pending[] = [
                'item' => $item,
                'before' => $item->description,
                'after' => preg_replace('/^Sablon Cup /', "Sablon {$noun} ", $item->description, 1),
                'reason' => "Kategori produk membutuhkan label {$noun}.",
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
            ['Invoice item', 'Product', 'Reason', 'Before', 'After'],
            array_map(static fn (array $row): array => [
                $row['item']->getKey(),
                $row['item']->product_name,
                $row['reason'],
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

    private function correctedCupDescription(InvoiceItem $item): ?string
    {
        $product = $item->product;

        // A historical snapshot is only safe to repair when it still points
        // at the same current product name. Otherwise the product may have
        // been renamed after issuance and guessing would rewrite history.
        if ($product === null || $item->product_name !== $product->name) {
            return null;
        }

        $productSize = trim((string) $product->cup_size);
        $description = trim((string) $item->description);

        if ($productSize === '' || ! str_starts_with(strtolower($description), 'sablon cup ')) {
            return null;
        }

        preg_match('/\b\d+(?:\/\d+)?\s*Oz\b/i', $product->name, $productNameSize);
        preg_match('/\b\d+(?:\/\d+)?\s*Oz\b/i', $description, $descriptionSize);

        if ($productNameSize === [] || $descriptionSize === []
            || $this->normalizedSize($productNameSize[0]) === $this->normalizedSize($descriptionSize[0])) {
            return null;
        }

        $snapshot = $item->replicate();
        $snapshot->cup_size = $product->cup_size;
        $snapshot->cup_model = $product->cup_model ?: $item->cup_model;
        $snapshot->grammage = $product->grammage ?: $item->grammage;
        $snapshot->screen_printing_color = $item->screen_printing_color ?: $product->screen_printing_color;
        $snapshot->jenis_cetak = $item->jenis_cetak ?: ($product->sides ? "{$product->sides} warna" : null);

        return $snapshot->cupSpecificationDescription();
    }

    private function normalizedSize(string $size): string
    {
        return strtolower((string) preg_replace('/\s+/', '', trim($size)));
    }
}
