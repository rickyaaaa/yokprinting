<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductIndexPageController extends Controller
{
    public function __invoke(): View
    {
        $models = Product::query()->withCount('invoiceItems')->orderBy('sku')->get();
        $costBasis = fn (Product $product): float => (float) ($product->average_purchase_cost ?? $product->last_purchase_price ?? $product->purchase_price);
        $products = $models->map(function (Product $product) use ($costBasis): array {
            $stock = (float) ($product->stock ?? 0);
            $minimum = $product->minimumStockValue();
            $lowStock = $product->track_stock && $stock <= $minimum;

            return [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category ?: '-',
                'unit' => strtoupper($product->unit),
                'purchasePrice' => $this->rupiah($costBasis($product)),
                'purchasePriceValue' => $costBasis($product),
                'stock' => $product->track_stock ? number_format($stock, 0, ',', '.').' '.strtoupper($product->unit) : 'Tidak dilacak',
                'stockValue' => $product->track_stock ? $stock : PHP_INT_MAX,
                'minimumStock' => $minimum,
                'sales' => $product->invoice_items_count,
                'status' => $product->status === Product::STATUS_INACTIVE ? 'Nonaktif' : ($lowStock ? 'Stok menipis' : 'Aktif'),
            ];
        })->values();

        $active = $models->where('status', Product::STATUS_ACTIVE);
        $lowStock = $active->filter(fn (Product $product): bool => $product->track_stock && (float) ($product->stock ?? 0) <= $product->minimumStockValue()
        );
        $inventoryValue = $models->sum(fn (Product $product): float => $product->track_stock ? (float) ($product->stock ?? 0) * $costBasis($product) : 0
        );
        $bestSeller = $models->sortByDesc('invoice_items_count')->first();

        return view('products.index', [
            'products' => $products,
            'summaryCards' => [
                ['label' => 'Total produk', 'value' => (string) $models->count(), 'caption' => $active->count().' aktif dijual', 'tone' => 'brand'],
                ['label' => 'Stok menipis', 'value' => (string) $lowStock->count(), 'caption' => 'Di bawah minimum', 'tone' => 'warning'],
                ['label' => 'Nilai persediaan', 'value' => $this->rupiah($inventoryValue), 'caption' => 'Berdasarkan biaya rata-rata stok', 'tone' => 'success'],
                ['label' => 'Produk terlaris', 'value' => $bestSeller?->name ?? '-', 'caption' => ($bestSeller?->invoice_items_count ?? 0).' transaksi', 'tone' => 'brand'],
            ],
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
