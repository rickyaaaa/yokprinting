<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class StockMovementReportController extends Controller
{
    public function __invoke(ListStockMovementReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now())->endOfDay();

        $products = Product::query()
            ->when($filters['product_id'] ?? null, fn ($query, int $productId) => $query->whereKey($productId))
            ->whereHas('stockMovements', fn ($query) => $query->where('created_at', '<=', $end))
            ->with([
                'stockMovements' => fn ($query) => $query->where('created_at', '<=', $end)->orderBy('created_at')->orderBy('id'),
                'inventoryBatches' => fn ($query) => $query->where('qty_remaining', '>', 0),
            ])
            ->orderBy('name')
            ->get();

        $rows = $products->map(function (Product $product) use ($start, $end): array {
            /** @var Collection<int, StockMovement> $movements */
            $movements = $product->stockMovements;
            $before = $movements->where('created_at', '<', $start);
            $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end);
            $adjustmentTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_STOCK_OPNAME];
            $regularMovements = $period->whereNotIn('type', $adjustmentTypes);
            $incoming = (float) $regularMovements->where('quantity', '>', 0)->sum('quantity');
            $outgoing = abs((float) $regularMovements->where('quantity', '<', 0)->sum('quantity'));
            $adjustments = (float) $period
                ->whereIn('type', $adjustmentTypes)
                ->sum('quantity');
            $opening = (float) $before->sum('quantity');
            $closing = (float) ($opening + $period->sum('quantity'));

            return [
                'product_id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'opening_balance' => round($opening, 4),
                'incoming_quantity' => round($incoming, 4),
                'outgoing_quantity' => round($outgoing, 4),
                'adjustments' => round($adjustments, 4),
                'closing_balance' => round($closing, 4),
                'current_stock' => $product->stock === null ? null : (float) $product->stock,
                'fifo_inventory_value' => round((float) $product->inventoryBatches->sum(
                    fn ($batch): float => (float) $batch->qty_remaining * (float) $batch->unit_cost,
                ), 2),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
                'summary' => [
                    'products_count' => $rows->count(),
                    'total_incoming' => round((float) $rows->sum('incoming_quantity'), 4),
                    'total_outgoing' => round((float) $rows->sum('outgoing_quantity'), 4),
                    'total_adjustments' => round((float) $rows->sum('adjustments'), 4),
                ],
                'products' => $rows,
            ],
        ]);
    }
}
