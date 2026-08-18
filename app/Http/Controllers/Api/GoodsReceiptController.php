<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListGoodsReceiptsRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\CreateGoodsReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class GoodsReceiptController extends Controller
{
    public function index(ListGoodsReceiptsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');

        $query = GoodsReceipt::query()
            ->with(['purchaseOrder:id,po_number,supplier_id', 'purchaseOrder.supplier:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['purchase_order_id'] ?? null, fn (Builder $query, int $poId): Builder => $query->where('purchase_order_id', $poId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('purchaseOrder', fn (Builder $query): Builder => $query->where('po_number', 'like', "%{$search}%"));
                });
            });

        $paginator = $query
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (GoodsReceipt $goodsReceipt): array => $this->serialize($goodsReceipt))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'reference' => [
                'statuses' => GoodsReceipt::statusLabels(),
            ],
        ]);
    }

    public function store(
        StoreGoodsReceiptRequest $request,
        PurchaseOrder $purchaseOrder,
        CreateGoodsReceipt $createGoodsReceipt,
    ): JsonResponse {
        $goodsReceipt = $createGoodsReceipt->handle(
            $purchaseOrder,
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Penerimaan barang berhasil disimpan sebagai draft.',
            'data' => $this->serialize($goodsReceipt, withItems: true),
        ], 201);
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $goodsReceipt->load(['purchaseOrder.supplier', 'items.product', 'creator:id,name', 'poster:id,name', 'canceller:id,name']);

        return response()->json([
            'data' => $this->serialize($goodsReceipt, withItems: true),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(GoodsReceipt $goodsReceipt, bool $withItems = false): array
    {
        $data = [
            'id' => $goodsReceipt->getKey(),
            'receipt_number' => $goodsReceipt->receipt_number,
            'purchase_order' => $goodsReceipt->relationLoaded('purchaseOrder') && $goodsReceipt->purchaseOrder ? [
                'id' => $goodsReceipt->purchaseOrder->getKey(),
                'po_number' => $goodsReceipt->purchaseOrder->po_number,
                'supplier_name' => $goodsReceipt->purchaseOrder->supplier?->name,
            ] : null,
            'receipt_date' => $goodsReceipt->receipt_date?->toDateString(),
            'status' => $goodsReceipt->status,
            'status_label' => $goodsReceipt->statusLabel(),
            'is_editable' => $goodsReceipt->isEditable(),
            'notes' => $goodsReceipt->notes,
            'created_by' => $goodsReceipt->relationLoaded('creator') ? $goodsReceipt->creator?->name : null,
            'posted_by' => $goodsReceipt->relationLoaded('poster') ? $goodsReceipt->poster?->name : null,
            'posted_at' => $goodsReceipt->posted_at?->toISOString(),
            'cancelled_by' => $goodsReceipt->relationLoaded('canceller') ? $goodsReceipt->canceller?->name : null,
            'cancelled_at' => $goodsReceipt->cancelled_at?->toISOString(),
            'created_at' => $goodsReceipt->created_at?->toISOString(),
        ];

        if ($withItems) {
            $data['items'] = $goodsReceipt->items->map(fn ($item): array => [
                'id' => $item->getKey(),
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity_received' => (float) $item->quantity_received,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values();
        }

        return $data;
    }
}
