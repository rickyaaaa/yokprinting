<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSupplierPriceListsRequest;
use App\Http\Requests\StoreSupplierPriceListRequest;
use App\Http\Requests\UpdateSupplierPriceListRequest;
use App\Models\SupplierPriceList;
use App\Models\User;
use App\Services\Purchasing\SaveSupplierPriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SupplierPriceListController extends Controller
{
    public function index(ListSupplierPriceListsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $today = Carbon::today();

        $todayString = $today->toDateString();

        $query = SupplierPriceList::query()
            ->with(['supplier:id,code,name', 'product:id,sku,name', 'creator:id,name'])
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('supplier_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('product_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('valid_from', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('valid_from', '<=', $date))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhere('source_reference', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            // Status is computed (valid_from/valid_until vs today), so it's
            // filtered here as an equivalent SQL condition rather than in
            // PHP after pagination - keeps meta.total/last_page accurate.
            ->when($filters['status'] ?? null, function (Builder $query, string $status) use ($todayString): void {
                match ($status) {
                    SupplierPriceList::STATUS_UPCOMING => $query->whereDate('valid_from', '>', $todayString),
                    SupplierPriceList::STATUS_EXPIRED => $query->whereNotNull('valid_until')->whereDate('valid_until', '<', $todayString),
                    default => $query->whereDate('valid_from', '<=', $todayString)
                        ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $todayString)),
                };
            });

        $paginator = $query
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (SupplierPriceList $priceList): array => $this->serialize($priceList, $today));

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'reference' => [
                'statuses' => SupplierPriceList::statusLabels(),
            ],
        ]);
    }

    public function store(StoreSupplierPriceListRequest $request, SaveSupplierPriceList $saveSupplierPriceList): JsonResponse
    {
        $data = $request->validated();
        $hasOverlap = $saveSupplierPriceList->hasOverlap($data);

        /** @var User|null $actor */
        $actor = $request->user();
        $priceList = $saveSupplierPriceList->create($data, $actor);

        return response()->json([
            'message' => 'Harga supplier berhasil disimpan.',
            'data' => $this->serialize($priceList),
            'warnings' => $hasOverlap ? [
                'overlap' => 'Rentang tanggal ini beririsan dengan entri harga lain untuk supplier dan produk yang sama.',
            ] : [],
        ], 201);
    }

    public function show(SupplierPriceList $supplierPrice): JsonResponse
    {
        $supplierPrice->load(['supplier', 'product', 'creator:id,name']);

        return response()->json([
            'data' => $this->serialize($supplierPrice) + [
                'is_used_in_purchase_order' => $supplierPrice->isUsedInPurchaseOrder(),
            ],
        ]);
    }

    public function update(
        UpdateSupplierPriceListRequest $request,
        SupplierPriceList $supplierPrice,
        SaveSupplierPriceList $saveSupplierPriceList,
    ): JsonResponse {
        /** @var User|null $actor */
        $actor = $request->user();
        $updated = $saveSupplierPriceList->update($supplierPrice, $request->validated(), $actor);

        return response()->json([
            'message' => 'Koreksi harga supplier berhasil disimpan.',
            'data' => $this->serialize($updated),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(SupplierPriceList $priceList, ?Carbon $today = null): array
    {
        return [
            'id' => $priceList->getKey(),
            'supplier' => $priceList->relationLoaded('supplier') && $priceList->supplier ? [
                'id' => $priceList->supplier->getKey(),
                'code' => $priceList->supplier->code,
                'name' => $priceList->supplier->name,
            ] : null,
            'product' => $priceList->relationLoaded('product') && $priceList->product ? [
                'id' => $priceList->product->getKey(),
                'sku' => $priceList->product->sku,
                'name' => $priceList->product->name,
            ] : null,
            'price' => (float) $priceList->price,
            'valid_from' => $priceList->valid_from?->toDateString(),
            'valid_until' => $priceList->valid_until?->toDateString(),
            'status' => $priceList->status($today),
            'status_label' => $priceList->statusLabel($today),
            'notes' => $priceList->notes,
            'source_reference' => $priceList->source_reference,
            'created_by' => $priceList->relationLoaded('creator') ? $priceList->creator?->name : null,
            'created_at' => $priceList->created_at?->toISOString(),
            'updated_at' => $priceList->updated_at?->toISOString(),
        ];
    }
}
