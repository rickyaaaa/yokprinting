<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductIndexPageController extends Controller
{
    public function __invoke(): View
    {
        $models = Product::query()
            // "Terjual"/"Produk terlaris" is the quantity from real
            // transactions, not the number of invoice rows. Previously a
            // 1,000-piece line counted as one sale. Cancelled invoices are
            // excluded by the same business-transaction scope used elsewhere.
            ->withSum([
                'invoiceItems as active_sales_quantity' => fn ($query) => $query
                    ->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->businessTransaction()),
            ], 'quantity')
            ->with('inventoryBatches')
            ->orderBy('sku')
            ->get();
        $products = $models->map(function (Product $product): array {
            $stock = (float) ($product->stock ?? 0);
            $minimum = $product->minimumStockValue();
            $lowStock = $product->track_stock && $stock <= $minimum;

            return [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category ?: '-',
                'unit' => strtoupper($product->unit),
                // "HPP FIFO": cost of the oldest available batch (what the
                // next sale draws from), NOT a weighted average; falls back to
                // the last purchase price when no batch is left - see
                // Product::fifoUnitCost().
                'purchasePrice' => $this->rupiah($product->fifoUnitCost()),
                'purchasePriceValue' => $product->fifoUnitCost(),
                'inventoryValue' => $product->fifoInventoryValue(),
                'stock' => $product->track_stock ? number_format($stock, 0, ',', '.').' '.strtoupper($product->unit) : 'Tidak dilacak',
                'stockValue' => $product->track_stock ? $stock : PHP_INT_MAX,
                'minimumStock' => $minimum,
                'sales' => $this->quantityValue($product->active_sales_quantity),
                'status' => $product->status === Product::STATUS_INACTIVE ? 'Nonaktif' : ($lowStock ? 'Stok menipis' : 'Aktif'),
            ];
        })->values();

        $active = $models->where('status', Product::STATUS_ACTIVE);
        $lowStock = $active->filter(fn (Product $product): bool => $product->track_stock && (float) ($product->stock ?? 0) <= $product->minimumStockValue()
        );
        $inventoryValue = $models->sum(fn (Product $product): float => $product->track_stock ? $product->fifoInventoryValue() : 0
        );
        // Oversold stock is worth nothing as an asset (fifoInventoryValue()
        // floors at zero), so what the company owes is surfaced next to the
        // total instead of silently netted off it.
        $shortfallValue = $models->sum(fn (Product $product): float => $product->track_stock ? $product->stockShortfallValue() : 0
        );
        $shortfallCount = $models->filter(fn (Product $product): bool => $product->track_stock && (float) ($product->stock ?? 0) < 0
        )->count();
        $bestSeller = $models->sortByDesc('active_sales_quantity')->first();

        return view('products.index', [
            'products' => $products,
            'summaryCards' => [
                ['label' => 'Total produk', 'value' => (string) $models->count(), 'caption' => $active->count().' aktif dijual', 'tone' => 'brand'],
                ['label' => 'Stok menipis', 'value' => (string) $lowStock->count(), 'caption' => 'Di bawah minimum', 'tone' => 'warning'],
                ['label' => 'Nilai persediaan', 'value' => $this->rupiah($inventoryValue), 'caption' => $shortfallCount > 0
                    ? "Kekurangan stok {$this->rupiah($shortfallValue)} di {$shortfallCount} produk"
                    : 'Berdasarkan layer HPP FIFO aktif', 'tone' => $shortfallCount > 0 ? 'warning' : 'success'],
                ['label' => 'Produk terlaris', 'value' => $bestSeller?->name ?? '-', 'caption' => $this->formatQuantity($bestSeller?->active_sales_quantity).' Pcs terjual', 'tone' => 'brand'],
            ],
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function quantityValue(mixed $quantity): int|float
    {
        $value = round((float) ($quantity ?? 0), 4);

        return fmod($value, 1.0) === 0.0 ? (int) $value : $value;
    }

    private function formatQuantity(mixed $quantity): string
    {
        $value = $this->quantityValue($quantity);

        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }
}
