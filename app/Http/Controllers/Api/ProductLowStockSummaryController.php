<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductLowStockSummaryController extends Controller
{
    /**
     * Return a summary of products that are below their minimum stock.
     */
    public function __invoke(): JsonResponse
    {
        $lowStockProducts = Product::query()
            ->with('categoryModel')
            ->lowStock()
            ->orderByRaw('(COALESCE(minimum_stock, ?) - stock) desc', [Product::DEFAULT_MINIMUM_STOCK])
            ->orderBy('name')
            ->get();

        $activeTrackedCount = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('track_stock', true)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'low_stock_count' => $lowStockProducts->count(),
                    'active_tracked_count' => $activeTrackedCount,
                    'healthy_stock_count' => max(0, $activeTrackedCount - $lowStockProducts->count()),
                    'needs_attention' => $lowStockProducts->isNotEmpty(),
                ],
                'products' => $lowStockProducts->map(fn (Product $product): array => [
                    'id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'category_detail' => $product->categoryModel ? [
                        'id' => $product->categoryModel->getKey(),
                        'name' => $product->categoryModel->name,
                        'slug' => $product->categoryModel->slug,
                    ] : null,
                    'unit' => $product->unit,
                    'stock' => (float) $product->stock,
                    'minimum_stock' => $product->minimumStockValue(),
                    'shortage' => max(0, $product->minimumStockValue() - (float) $product->stock),
                    'status' => $product->status,
                ])->values(),
            ],
        ]);
    }
}
