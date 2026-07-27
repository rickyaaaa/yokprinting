<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductOptionsRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProductOptionController extends Controller
{
    /**
     * Return active products for transactional dropdowns.
     */
    public function index(ListProductOptionsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? '');
        $limit = (int) ($validated['limit'] ?? 100);

        $products = Product::query()
            ->select([
                'id',
                'name',
                'sku',
                'category',
                'brand',
                'short_description',
                'purchase_price',
                'stock',
                'minimum_order_qty',
                'package_conversion',
                'unit',
            ])
            ->selectable()
            ->when(
                filled($validated['ids'] ?? null),
                fn (Builder $query): Builder => $query->whereIn('id', $validated['ids']),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category,
                'brand' => $product->brand,
                'short_description' => $product->short_description,
                'purchase_price' => (float) $product->purchase_price,
                'stock' => $product->stock === null ? null : (float) $product->stock,
                'minimum_order_qty' => $product->minimum_order_qty,
                'package_conversion' => $product->package_conversion,
                'unit' => $product->unit,
            ])
            ->values();

        return response()->json([
            'data' => $products,
            'meta' => [
                'count' => $products->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
