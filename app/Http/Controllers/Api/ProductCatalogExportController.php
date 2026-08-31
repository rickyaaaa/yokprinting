<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportProductCatalogRequest;
use App\Models\Product;
use App\Services\Reports\GeneratedReportFile;
use App\Services\Reports\GenerateProductCatalogSpreadsheet;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Export the product catalogue as it appears on the product list page.
 *
 * Distinct from the stock-mutation export (StockReportExportController), which
 * is a period ledger and only covers products that actually moved. This is a
 * point-in-time snapshot of every product, which is what "tarikan data produk
 * atau stok" asks for.
 */
class ProductCatalogExportController extends Controller
{
    private const HEADERS = [
        'SKU', 'Nama Produk', 'Kategori', 'Unit', 'HPP FIFO',
        'Stok', 'Minimum Stok', 'Status', 'Nilai Persediaan',
    ];

    public function excel(
        ExportProductCatalogRequest $request,
        GenerateProductCatalogSpreadsheet $spreadsheet,
    ): Response {
        $filters = $request->validated();
        $rows = $this->rows($filters);

        return $this->download($spreadsheet->generate(
            $rows,
            $this->totals($rows),
            $this->filterSummary($filters),
        ));
    }

    public function pdf(ExportProductCatalogRequest $request): Response
    {
        $filters = $request->validated();
        $rows = $this->rows($filters);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.products', [
            'rows' => $rows,
            'headers' => self::HEADERS,
            'generatedAt' => CarbonImmutable::now((string) config('app.timezone', 'UTC')),
            'filterSummary' => $this->filterSummary($filters),
            'totals' => $this->totals($rows),
        ])->render(), 'UTF-8');
        $dompdf->render();

        return $this->download(new GeneratedReportFile(
            $dompdf->output(),
            'data-produk-'.CarbonImmutable::now((string) config('app.timezone', 'UTC'))->format('Y-m-d').'.pdf',
            'application/pdf',
        ));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows): array
    {
        return [
            'product_count' => $rows->count(),
            'inventory_value' => round((float) $rows->sum('inventory_value'), 2),
            'shortfall_value' => round((float) $rows->sum('shortfall_value'), 2),
            'shortfall_count' => $rows->where('shortfall_value', '>', 0)->count(),
        ];
    }

    /**
     * Spell out which filters produced this file, so a saved export is never
     * mistaken for the full catalogue.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filterSummary(array $filters): ?string
    {
        $parts = [];
        $status = $filters['status'] ?? 'all';

        if ($status !== 'all') {
            $parts[] = 'Status: '.match ($status) {
                'active' => 'Aktif',
                'low_stock' => 'Stok menipis',
                'inactive' => 'Nonaktif',
                default => $status,
            };
        }

        if (($filters['category'] ?? null) !== null && trim((string) $filters['category']) !== '') {
            $parts[] = 'Kategori: '.trim((string) $filters['category']);
        }

        if (($filters['q'] ?? null) !== null && trim((string) $filters['q']) !== '') {
            $parts[] = 'Pencarian: "'.trim((string) $filters['q']).'"';
        }

        return $parts === [] ? null : 'Filter - '.implode(' | ', $parts);
    }

    private function download(GeneratedReportFile $file): Response
    {
        return response($file->contents, 200, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
            'Content-Length' => (string) strlen($file->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        $statusFilter = $filters['status'] ?? 'all';
        $keyword = trim((string) ($filters['q'] ?? ''));

        return Product::query()
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
                    'shortfall_value' => $product->track_stock ? $product->stockShortfallValue() : 0.0,
                ];
            })
            ->filter(fn (array $row): bool => match ($statusFilter) {
                'active' => $row['status'] === 'Aktif',
                'low_stock' => $row['status'] === 'Stok menipis',
                'inactive' => $row['status'] === 'Nonaktif',
                default => true,
            })
            ->values();
    }
}
