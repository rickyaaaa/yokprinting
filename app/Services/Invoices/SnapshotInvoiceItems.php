<?php

namespace App\Services\Invoices;

use App\Models\InvoiceItem;
use App\Models\Product;

class SnapshotInvoiceItems
{
    /**
     * Snapshot mutable product procurement data at invoice save time so a
     * later product rename/price change never rewrites this invoice's
     * items. Shared between CreateInvoiceDraft and UpdateInvoiceDraft.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function handle(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products): array {
                $product = $products->get($item['product_id'] ?? null);

                if ($product instanceof Product) {
                    // The linked catalogue product is authoritative for the
                    // identity snapshot. A stale hidden input must never
                    // turn the product name into its generated description
                    // or carry a 12 Oz spec into a 14 Oz product invoice.
                    $item['product_name'] = $product->name;
                    $item['sku'] = $product->sku;
                    $item['cup_size'] = $product->cup_size ?: ($item['cup_size'] ?? null);
                    $item['cup_model'] = $product->cup_model ?: ($item['cup_model'] ?? null);
                    $item['grammage'] = $product->grammage ?: ($item['grammage'] ?? null);
                    $item['screen_printing_color'] = $item['screen_printing_color'] ?? $product->screen_printing_color;
                    $item['jenis_cetak'] = $item['jenis_cetak']
                        ?? ($product->sides ? "{$product->sides} warna" : null);
                    $item['description'] = $this->normalizeGeneratedDescription($item, $product);
                    $item['order_increment'] = $product->package_conversion ?: $product->order_increment ?: 500;
                    $item['moq_quantity'] = $item['order_increment'];
                    $item['packaging_unit'] = $product->unit ?: Product::UNIT_PCS;
                    // Prefer the purchasing-module cost basis (built from real
                    // PO/Goods Receipt prices) over the legacy flat field,
                    // which nothing writes to anymore. Once written, this
                    // snapshot never changes even if the product's cost does.
                    $item['purchase_cost_snapshot'] = $product->track_stock
                        ? 0
                        : ($product->average_purchase_cost
                            ?? $product->last_purchase_price
                            ?? $product->purchase_price);
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Correct a stale generated spec such as "Sablon Cup 12 Oz ..." when the
     * selected product is a 14 Oz product. Arbitrary/custom descriptions are
     * left alone unless they clearly follow the generated Cup format.
     *
     * @param  array<string, mixed>  $item
     */
    private function normalizeGeneratedDescription(array $item, Product $product): ?string
    {
        $description = trim((string) ($item['description'] ?? ''));

        if ($description === '' || ! str_starts_with(strtolower($description), 'sablon cup ')
            || ! $product->cup_size) {
            return $item['description'] ?? null;
        }

        preg_match('/\b\d+(?:\/\d+)?\s*Oz\b/i', $product->name, $productNameSize);
        preg_match('/\b\d+(?:\/\d+)?\s*Oz\b/i', $description, $descriptionSize);

        if ($productNameSize === [] || $descriptionSize === []
            || $this->normalizedSize($productNameSize[0]) === $this->normalizedSize($descriptionSize[0])) {
            return $item['description'] ?? null;
        }

        $snapshot = new InvoiceItem($item);
        $snapshot->product_name = $product->name;
        $snapshot->cup_size = $product->cup_size;
        $snapshot->cup_model = $product->cup_model ?: ($item['cup_model'] ?? null);
        $snapshot->grammage = $product->grammage ?: ($item['grammage'] ?? null);
        $snapshot->screen_printing_color = $item['screen_printing_color'] ?? $product->screen_printing_color;
        $snapshot->jenis_cetak = $item['jenis_cetak'] ?? ($product->sides ? "{$product->sides} warna" : null);

        return $snapshot->cupSpecificationDescription();
    }

    private function normalizedSize(string $size): string
    {
        return strtolower((string) preg_replace('/\s+/', '', trim($size)));
    }
}
