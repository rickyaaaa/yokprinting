<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSuppliersRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * List suppliers with optional search.
     */
    public function index(ListSuppliersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? $validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 100);

        $suppliers = Supplier::query()
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Supplier $supplier): array => $this->serializeSupplier($supplier))
            ->values();

        return response()->json([
            'data' => $suppliers,
            'meta' => [
                'count' => $suppliers->count(),
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        return response()->json([
            'data' => $this->serializeSupplier($supplier),
            'message' => 'Supplier created successfully.',
        ], 201);
    }

    /**
     * Display a supplier.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeSupplier($supplier->load('products')),
        ]);
    }

    /**
     * Update a supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json([
            'data' => $this->serializeSupplier($supplier->refresh()),
            'message' => 'Supplier updated successfully.',
        ]);
    }

    /**
     * Soft delete a supplier.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(status: 204);
    }

    /**
     * Transform a supplier model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeSupplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->getKey(),
            'code' => $supplier->code,
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'products_count' => $supplier->products_count ?? null,
            'products' => $supplier->relationLoaded('products')
                ? $supplier->products->map(fn ($product): array => [
                    'id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'purchase_price' => (float) $product->pivot->purchase_price,
                    'minimum_purchase' => $product->pivot->minimum_purchase,
                    'supplier_unit' => $product->pivot->supplier_unit,
                    'is_primary' => (bool) $product->pivot->is_primary,
                ])->values()
                : null,
            'created_at' => $supplier->created_at?->toISOString(),
            'updated_at' => $supplier->updated_at?->toISOString(),
        ];
    }
}
