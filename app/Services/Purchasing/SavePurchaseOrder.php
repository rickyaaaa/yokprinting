<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePurchaseOrder
{
    public function __construct(private readonly GeneratePurchaseOrderNumber $generatePurchaseOrderNumber) {}

    /**
     * Create a new draft purchase order with its line items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $creatorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $creatorId): PurchaseOrder {
            $items = $this->snapshotItems($data['items']);
            $totals = $this->calculateTotals($items, $data);

            $purchaseOrder = PurchaseOrder::query()->create([
                'po_number' => $this->generatePurchaseOrderNumber->generate(),
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'subtotal' => $totals['subtotal'],
                'shipping_cost' => $totals['shipping_cost'],
                'other_cost' => $totals['other_cost'],
                'grand_total' => $totals['grand_total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $creatorId,
            ]);

            $purchaseOrder->items()->createMany($items);

            return $purchaseOrder->load(['items', 'supplier']);
        });
    }

    /**
     * Replace a draft purchase order's header and line items. Only allowed
     * while the PO is still a draft - once submitted, prices are locked.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'PO yang sudah diajukan/disetujui tidak bisa diedit. Batalkan lalu buat PO baru kalau harga/qty perlu berubah.',
                ]);
            }

            $items = $this->snapshotItems($data['items']);
            $totals = $this->calculateTotals($items, $data);

            $locked->update([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'subtotal' => $totals['subtotal'],
                'shipping_cost' => $totals['shipping_cost'],
                'other_cost' => $totals['other_cost'],
                'grand_total' => $totals['grand_total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->items()->delete();
            $locked->items()->createMany($items);

            return $locked->refresh()->load(['items', 'supplier']);
        });
    }

    /**
     * Snapshot product data at order time so a later product rename/deletion
     * never rewrites this PO's history.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function snapshotItems(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products): array {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items' => "Produk dengan ID {$item['product_id']} tidak ditemukan.",
                    ]);
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                return [
                    'product_id' => $product->getKey(),
                    'product_name_snapshot' => $product->name,
                    'sku_snapshot' => $product->sku,
                    'unit_snapshot' => $product->unit,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => round($quantity * $unitPrice, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array{subtotal: float, shipping_cost: float, other_cost: float, grand_total: float}
     */
    private function calculateTotals(array $items, array $data): array
    {
        $subtotal = round(array_sum(array_column($items, 'subtotal')), 2);
        $shippingCost = round((float) ($data['shipping_cost'] ?? 0), 2);
        $otherCost = round((float) ($data['other_cost'] ?? 0), 2);

        return [
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'other_cost' => $otherCost,
            'grand_total' => round($subtotal + $shippingCost + $otherCost, 2),
        ];
    }
}
