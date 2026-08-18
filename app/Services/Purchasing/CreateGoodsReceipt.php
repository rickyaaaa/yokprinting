<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGoodsReceipt
{
    public function __construct(private readonly GenerateGoodsReceiptNumber $generateGoodsReceiptNumber) {}

    /**
     * Create a draft goods receipt against an approved PO. Stock/cost are
     * untouched until the receipt is posted.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(PurchaseOrder $purchaseOrder, array $data, ?int $creatorId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $creatorId): GoodsReceipt {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if (! in_array($lockedPo->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Penerimaan barang hanya bisa dibuat untuk PO yang sudah disetujui.',
                ]);
            }

            $poItems = $lockedPo->items()->lockForUpdate()->get()->keyBy('id');
            $items = $this->buildItems($data['items'], $poItems);

            $goodsReceipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->generateGoodsReceiptNumber->generate(),
                'purchase_order_id' => $lockedPo->getKey(),
                'receipt_date' => $data['receipt_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $creatorId,
            ]);

            $goodsReceipt->items()->createMany($items);

            return $goodsReceipt->load(['items', 'purchaseOrder.supplier']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $requestedItems
     * @param  Collection<int, PurchaseOrderItem>  $poItems
     * @return list<array<string, mixed>>
     */
    private function buildItems(array $requestedItems, $poItems): array
    {
        $items = [];

        foreach ($requestedItems as $requested) {
            $quantity = (float) ($requested['quantity_received'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            /** @var PurchaseOrderItem|null $poItem */
            $poItem = $poItems->get($requested['purchase_order_item_id']);

            if (! $poItem instanceof PurchaseOrderItem) {
                throw ValidationException::withMessages([
                    'items' => 'Salah satu item bukan bagian dari PO ini.',
                ]);
            }

            $remaining = round((float) $poItem->quantity - (float) $poItem->received_quantity, 4);

            if ($quantity > $remaining) {
                throw ValidationException::withMessages([
                    'items' => "Jumlah diterima untuk {$poItem->product_name_snapshot} ({$quantity}) melebihi sisa PO ({$remaining}).",
                ]);
            }

            $unitPrice = (float) $poItem->unit_price;

            $items[] = [
                'purchase_order_item_id' => $poItem->getKey(),
                'product_id' => $poItem->product_id,
                'quantity_received' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Isi minimal satu jumlah barang yang diterima.',
            ]);
        }

        return $items;
    }
}
