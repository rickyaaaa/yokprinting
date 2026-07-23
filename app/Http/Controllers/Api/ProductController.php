<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * List products with search and filters.
     */
    public function index(ListProductsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? $validated['q'] ?? '');
        $status = $validated['status'] ?? Product::STATUS_ACTIVE;
        $limit = (int) ($validated['limit'] ?? 100);
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $products = Product::query()
            ->with('categoryModel')
            ->when(
                filled($validated['ids'] ?? null),
                fn (Builder $query): Builder => $query->whereIn('id', $validated['ids']),
            )
            ->when(
                $status !== 'all',
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when(
                filled($validated['category_id'] ?? null),
                fn (Builder $query): Builder => $query->where('category_id', $validated['category_id']),
            )
            ->when(
                filled($validated['category'] ?? null),
                fn (Builder $query): Builder => $query->where('category', $validated['category']),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('categoryModel', fn (Builder $categoryQuery): Builder => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();

        return response()->json([
            'data' => $products,
            'meta' => [
                'count' => $products->count(),
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'category' => $validated['category'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                ],
                'sort' => [
                    'key' => $sort,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($this->normalizeCategory($request->validated()));

        return response()->json([
            'data' => $this->serializeProduct($product->load('categoryModel')),
            'message' => 'Product created successfully.',
        ], 201);
    }

    /**
     * Display a product.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeProduct($product->load('categoryModel')),
        ]);
    }

    /**
     * Update a product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($this->normalizeCategory($request->validated()));

        return response()->json([
            'data' => $this->serializeProduct($product->refresh()->load('categoryModel')),
            'message' => 'Product updated successfully.',
        ]);
    }

    /**
     * Soft delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(status: 204);
    }

    /**
     * Keep the legacy category label in sync with normalized category data.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCategory(array $payload): array
    {
        if (array_key_exists('category_id', $payload) && ! array_key_exists('category', $payload)) {
            $payload['category'] = ProductCategory::query()->find($payload['category_id'])?->name;
        }

        return $payload;
    }

    /**
     * Transform a product model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getKey(),
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category' => $product->category,
            'category_detail' => $product->categoryModel ? [
                'id' => $product->categoryModel->getKey(),
                'name' => $product->categoryModel->name,
                'slug' => $product->categoryModel->slug,
            ] : null,
            'description' => $product->description,
            'unit' => $product->unit,
            'price' => (float) $product->price,
            'stock' => $product->stock === null ? null : (float) $product->stock,
            'minimum_stock' => $product->minimum_stock === null ? null : (float) $product->minimum_stock,
            'track_stock' => $product->track_stock,
            'status' => $product->status,
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }
}
