<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class StockReportExportController extends Controller
{
    public function csv(ListStockMovementReportRequest $request, ReportCsvExport $export): Response
    {
        [$rows, $start, $end] = $this->rows($request->validated());

        return $export->download(
            "laporan-stok-{$start->toDateString()}-sampai-{$end->toDateString()}.csv",
            ['SKU', 'Product Name', 'Category', 'Unit', 'Opening Stock', 'Purchase Qty', 'Sales Qty', 'Adjustment', 'Ending Stock', 'FIFO Inventory Value'],
            $rows,
        );
    }

    public function pdf(ListStockMovementReportRequest $request): Response
    {
        [$rows, $start, $end] = $this->rows($request->validated());
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.stock', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
        ])->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"laporan-stok-{$start->toDateString()}-sampai-{$end->toDateString()}.pdf\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{0: array<int, array<int, mixed>>, 1: CarbonImmutable, 2: CarbonImmutable} */
    private function rows(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now())->endOfDay();
        $adjustmentTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_STOCK_OPNAME];

        $rows = Product::query()
            ->when($filters['product_id'] ?? null, fn ($query, int $productId) => $query->whereKey($productId))
            ->whereHas('stockMovements', fn ($query) => $query->where('created_at', '<=', $end))
            ->with([
                'stockMovements' => fn ($query) => $query->where('created_at', '<=', $end)->orderBy('created_at')->orderBy('id'),
                'inventoryBatches' => fn ($query) => $query->where('qty_remaining', '>', 0),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($start, $end, $adjustmentTypes): array {
                $movements = $product->stockMovements;
                $opening = (float) $movements->where('created_at', '<', $start)->sum('quantity');
                $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end);
                $regular = $period->whereNotIn('type', $adjustmentTypes);
                $incoming = (float) $regular->where('quantity', '>', 0)->sum('quantity');
                $outgoing = abs((float) $regular->where('quantity', '<', 0)->sum('quantity'));
                $adjustments = (float) $period->whereIn('type', $adjustmentTypes)->sum('quantity');
                $ending = round($opening + (float) $period->sum('quantity'), 4);
                $fifoValue = round((float) $product->inventoryBatches->sum(
                    fn ($batch): float => (float) $batch->qty_remaining * (float) $batch->unit_cost,
                ), 2);

                return [
                    $product->sku,
                    $product->name,
                    $product->category ?: '-',
                    $product->unit,
                    round($opening, 4),
                    round($incoming, 4),
                    round($outgoing, 4),
                    round($adjustments, 4),
                    $ending,
                    $fifoValue,
                ];
            })->all();

        return [$rows, $start, $end];
    }
}
