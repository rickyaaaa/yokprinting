<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListPurchaseOrdersRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\SavePurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function index(ListPurchaseOrdersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');

        $query = PurchaseOrder::query()
            ->with('supplier:id,code,name')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, int $supplierId): Builder => $query->where('supplier_id', $supplierId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $paginator = $query
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (PurchaseOrder $purchaseOrder): array => $this->serialize($purchaseOrder))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'reference' => [
                'statuses' => PurchaseOrder::statusLabels(),
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, SavePurchaseOrder $savePurchaseOrder): JsonResponse
    {
        $purchaseOrder = $savePurchaseOrder->create(
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'PO berhasil disimpan sebagai draft.',
            'data' => $this->serialize($purchaseOrder, withItems: true),
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'items.product', 'creator:id,name', 'approver:id,name', 'canceller:id,name']);

        return response()->json([
            'data' => $this->serialize($purchaseOrder, withItems: true),
        ]);
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        SavePurchaseOrder $savePurchaseOrder,
    ): JsonResponse {
        $updated = $savePurchaseOrder->update($purchaseOrder, $request->validated());

        return response()->json([
            'message' => 'PO berhasil diperbarui.',
            'data' => $this->serialize($updated, withItems: true),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(PurchaseOrder $purchaseOrder, bool $withItems = false): array
    {
        $data = [
            'id' => $purchaseOrder->getKey(),
            'po_number' => $purchaseOrder->po_number,
            'supplier' => $purchaseOrder->relationLoaded('supplier') && $purchaseOrder->supplier ? [
                'id' => $purchaseOrder->supplier->getKey(),
                'code' => $purchaseOrder->supplier->code,
                'name' => $purchaseOrder->supplier->name,
            ] : null,
            'order_date' => $purchaseOrder->order_date?->toDateString(),
            'expected_date' => $purchaseOrder->expected_date?->toDateString(),
            'currency' => $purchaseOrder->currency,
            'subtotal' => (float) $purchaseOrder->subtotal,
            'shipping_cost' => (float) $purchaseOrder->shipping_cost,
            'other_cost' => (float) $purchaseOrder->other_cost,
            'grand_total' => (float) $purchaseOrder->grand_total,
            'status' => $purchaseOrder->status,
            'status_label' => $purchaseOrder->statusLabel(),
            'is_editable' => $purchaseOrder->isEditable(),
            'can_be_cancelled' => $purchaseOrder->canBeCancelled(),
            'notes' => $purchaseOrder->notes,
            'created_by' => $purchaseOrder->relationLoaded('creator') ? $purchaseOrder->creator?->name : null,
            'approved_by' => $purchaseOrder->relationLoaded('approver') ? $purchaseOrder->approver?->name : null,
            'approved_at' => $purchaseOrder->approved_at?->toISOString(),
            'cancelled_by' => $purchaseOrder->relationLoaded('canceller') ? $purchaseOrder->canceller?->name : null,
            'cancelled_at' => $purchaseOrder->cancelled_at?->toISOString(),
            'cancellation_reason' => $purchaseOrder->cancellation_reason,
            'created_at' => $purchaseOrder->created_at?->toISOString(),
        ];

        if ($withItems) {
            $data['items'] = $purchaseOrder->items->map(fn ($item): array => [
                'id' => $item->getKey(),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name_snapshot,
                'sku' => $item->sku_snapshot,
                'unit' => $item->unit_snapshot,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'received_quantity' => (float) $item->received_quantity,
            ])->values();
        }

        return $data;
    }
}
