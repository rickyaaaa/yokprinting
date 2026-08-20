<?php

namespace App\Services\Invoices;

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
                    $item['product_name'] = $item['product_name'] ?? $product->name;
                    $item['sku'] = $item['sku'] ?? $product->sku;
                    $item['cup_size'] = $item['cup_size'] ?? $product->cup_size;
                    $item['cup_model'] = $item['cup_model'] ?? $product->cup_model;
                    $item['grammage'] = $item['grammage'] ?? $product->grammage;
                    $item['screen_printing_color'] = $item['screen_printing_color'] ?? $product->screen_printing_color;
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
}
