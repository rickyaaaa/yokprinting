<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SupplierPriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

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
        $limit = (int) ($validated['limit'] ?? 150);
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $products = Product::query()
            ->with(['categoryModel', 'inventoryBatches'])
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
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('categoryModel', fn (Builder $categoryQuery): Builder => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->limit($limit)
            ->get();

        $lastSupplierPrices = $this->lastSupplierPricesFor($products->pluck('id'));

        $products = $products
            ->map(fn (Product $product): array => $this->serializeProduct($product, $lastSupplierPrices->get($product->getKey())))
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
            'data' => $this->serializeProduct($product->load(['categoryModel', 'inventoryBatches'])),
            'message' => 'Product created successfully.',
        ], 201);
    }

    /**
     * Display a product.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeProduct($product->load(['categoryModel', 'inventoryBatches'])),
        ]);
    }

    /**
     * Update a product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($this->normalizeCategory($request->validated()));

        return response()->json([
            'data' => $this->serializeProduct($product->refresh()->load(['categoryModel', 'inventoryBatches'])),
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
        if (array_key_exists('code', $payload) && ! array_key_exists('sku', $payload)) {
            $payload['sku'] = $payload['code'];
        }

        unset($payload['code']);

        if (array_key_exists('category_id', $payload) && ! array_key_exists('category', $payload)) {
            $payload['category'] = ProductCategory::query()->find($payload['category_id'])?->name;
        }

        if (array_key_exists('minimum_stock', $payload) && $payload['minimum_stock'] === null) {
            $payload['minimum_stock'] = Product::DEFAULT_MINIMUM_STOCK;
        }

        return $payload;
    }

    /**
     * Transform a product model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product, ?SupplierPriceList $lastSupplierPrice = null): array
    {
        return [
            'id' => $product->getKey(),
            'code' => $product->code,
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category' => $product->category,
            'brand' => $product->brand,
            'cup_size' => $product->cup_size,
            'cup_model' => $product->cup_model,
            'grammage' => $product->grammage,
            'screen_printing_color' => $product->screen_printing_color,
            'sides' => $product->sides,
            'cup_description' => $product->cupDescription(),
            'category_detail' => $product->categoryModel ? [
                'id' => $product->categoryModel->getKey(),
                'name' => $product->categoryModel->name,
                'slug' => $product->categoryModel->slug,
            ] : null,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'unit' => $product->unit,
            'purchase_price' => (float) $product->purchase_price,
            'last_purchase_price' => $product->last_purchase_price === null ? null : (float) $product->last_purchase_price,
            'average_purchase_cost' => $product->average_purchase_cost === null ? null : (float) $product->average_purchase_cost,
            // The "HPP FIFO" value: unit cost of the oldest available FIFO
            // batch (what the next sale will actually draw from), NOT a
            // weighted average across remaining batches. Falls back to the
            // average-cost chain above only while no batch is available.
            // See Product::fifoUnitCost()/fifoInventoryValue().
            'fifo_hpp' => (float) $product->fifoUnitCost(),
            'fifo_inventory_value' => (float) $product->fifoInventoryValue(),
            // From Supplier Price List, not from actual purchase transactions -
            // deliberately kept separate from last_purchase_price above (see
            // requirement 14/15: supplier's asking price vs what was actually paid).
            'last_supplier_price' => $this->serializeLastSupplierPrice($lastSupplierPrice ?? $this->lastSupplierPricesFor(collect([$product->getKey()]))->get($product->getKey())),
            'stock' => $product->stock === null ? null : (float) $product->stock,
            'minimum_stock' => $product->minimumStockValue(),
            'minimum_order_qty' => $product->minimum_order_qty,
            'package_conversion' => $product->package_conversion,
            'length_cm' => $product->length_cm === null ? null : (float) $product->length_cm,
            'width_cm' => $product->width_cm === null ? null : (float) $product->width_cm,
            'height_cm' => $product->height_cm === null ? null : (float) $product->height_cm,
            'weight_gram' => $product->weight_gram === null ? null : (float) $product->weight_gram,
            'dimensions' => $product->dimensions,
            'moq_quantity' => $product->moq_quantity,
            'order_increment' => $product->order_increment,
            'packaging_unit' => $product->packaging_unit,
            'track_stock' => $product->track_stock,
            'status' => $product->status,
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }

    /**
     * Batch-resolve the most recent Supplier Price List quote per product
     * (across all suppliers) in a single query, keyed by product id - a
     * read-only reference shown on the product master (requirement 14).
     * Compare suppliers side by side on the Harga Supplier page instead.
     *
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, SupplierPriceList>
     */
    private function lastSupplierPricesFor(Collection $productIds): Collection
    {
        $productIds = $productIds->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return SupplierPriceList::query()
            ->whereIn('product_id', $productIds)
            ->with('supplier:id,name')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($group) => $group->first());
    }

    /** @return array<string, mixed>|null */
    private function serializeLastSupplierPrice(?SupplierPriceList $priceList): ?array
    {
        if (! $priceList) {
            return null;
        }

        return [
            'price' => (float) $priceList->price,
            'supplier_name' => $priceList->supplier?->name,
            'valid_from' => $priceList->valid_from?->toDateString(),
            'valid_until' => $priceList->valid_until?->toDateString(),
            'status' => $priceList->status(),
        ];
    }
}
