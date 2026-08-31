<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportProductCatalogRequest;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * Export the product catalog as it appears on the product list page.
 *
 * Distinct from the stock-mutation export (StockReportExportController), which
 * is a period ledger and only covers products that actually moved. This is a
 * point-in-time snapshot of every product, which is what "tarikan data produk
 * atau stok" asks for.
 */
class ProductCatalogExportController extends Controller
{
    public function __invoke(ExportProductCatalogRequest $request, ReportCsvExport $export): Response
    {
        $filters = $request->validated();
        $statusFilter = $filters['status'] ?? 'all';
        $keyword = trim((string) ($filters['q'] ?? ''));

        $rows = Product::query()
            ->with(['inventoryBatches' => fn ($query) => $query->where('qty_remaining', '>', 0)])
            ->when(($filters['category'] ?? null), fn ($query, string $category) => $query->where('category', $category))
            ->when($keyword !== '', fn ($query) => $query->where(function ($query) use ($keyword): void {
                $query
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('category', 'like', "%{$keyword}%");
            }))
            ->orderBy('sku')
            ->get()
            // Status label mirrors ProductIndexPageController exactly, so the
            // exported file and the screen never disagree.
            ->map(function (Product $product): array {
                $stock = (float) ($product->stock ?? 0);
                $minimum = $product->minimumStockValue();
                $lowStock = $product->track_stock && $stock <= $minimum;

                return [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category ?: '-',
                    'unit' => strtoupper($product->unit),
                    'fifo_unit_cost' => $product->fifoUnitCost(),
                    'stock' => $product->track_stock ? $stock : null,
                    'minimum_stock' => $product->track_stock ? $minimum : null,
                    'status' => $product->status === Product::STATUS_INACTIVE
                        ? 'Nonaktif'
                        : ($lowStock ? 'Stok menipis' : 'Aktif'),
                    'inventory_value' => $product->track_stock ? $product->fifoInventoryValue() : 0.0,
                ];
            })
            ->filter(fn (array $row): bool => match ($statusFilter) {
                'active' => $row['status'] === 'Aktif',
                'low_stock' => $row['status'] === 'Stok menipis',
                'inactive' => $row['status'] === 'Nonaktif',
                default => true,
            })
            ->map(fn (array $row): array => [
                $row['sku'],
                $row['name'],
                $row['category'],
                $row['unit'],
                $row['fifo_unit_cost'],
                $row['stock'] ?? 'Tidak dilacak',
                $row['minimum_stock'] ?? 'Tidak dilacak',
                $row['status'],
                $row['inventory_value'],
            ])
            ->values();

        return $export->download(
            'data-produk-'.CarbonImmutable::now((string) config('app.timezone', 'UTC'))->format('Y-m-d').'.csv',
            ['SKU', 'Nama Produk', 'Kategori', 'Unit', 'HPP FIFO', 'Stok', 'Minimum Stok', 'Status', 'Nilai Persediaan'],
            $rows,
        );
    }
}
