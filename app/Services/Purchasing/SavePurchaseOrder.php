<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SupplierPriceList;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePurchaseOrder
{
    public function __construct(
        private readonly GeneratePurchaseOrderNumber $generatePurchaseOrderNumber,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Create a new draft purchase order with its line items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $creatorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $creatorId): PurchaseOrder {
            $items = $this->snapshotItems($data['items'], (int) $data['supplier_id']);
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

            $this->logSupplierPriceUsage($purchaseOrder, $items);

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

            $items = $this->snapshotItems($data['items'], (int) $data['supplier_id']);
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

            $this->logSupplierPriceUsage($locked, $items);

            return $locked->refresh()->load(['items', 'supplier']);
        });
    }

    /**
     * Snapshot product data at order time so a later product rename/deletion
     * never rewrites this PO's history. unit_price is always taken verbatim
     * from the request - it's the negotiated transaction price and is never
     * recalculated from the referenced Supplier Price List quote, even when
     * one is attached.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function snapshotItems(array $items, int $supplierId): array
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $priceListIds = collect($items)->pluck('supplier_price_list_id')->filter()->unique()->values();
        $priceLists = $priceListIds->isEmpty()
            ? collect()
            : SupplierPriceList::query()->whereIn('id', $priceListIds)->get()->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products, $priceLists, $supplierId): array {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items' => "Produk dengan ID {$item['product_id']} tidak ditemukan.",
                    ]);
                }

                $priceListId = $item['supplier_price_list_id'] ?? null;

                if ($priceListId !== null) {
                    /** @var SupplierPriceList|null $priceList */
                    $priceList = $priceLists->get($priceListId);

                    if (! $priceList instanceof SupplierPriceList
                        || $priceList->supplier_id !== $supplierId
                        || $priceList->product_id !== $product->getKey()
                    ) {
                        throw ValidationException::withMessages([
                            'items' => 'Referensi harga supplier tidak cocok dengan supplier/produk pada baris ini.',
                        ]);
                    }
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                return [
                    'product_id' => $product->getKey(),
                    'supplier_price_list_id' => $priceListId,
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
     * Log which Supplier Price List quotes (if any) were used as the basis
     * for this PO's items - reference/audit only, doesn't affect pricing.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function logSupplierPriceUsage(PurchaseOrder $purchaseOrder, array $items): void
    {
        $usedPriceListIds = collect($items)->pluck('supplier_price_list_id')->filter()->unique()->values();

        if ($usedPriceListIds->isEmpty()) {
            return;
        }

        $this->activityLogger->record(
            module: 'purchase_order',
            action: 'used_supplier_price',
            event: 'Purchase order created using supplier price list suggestion',
            description: "PO {$purchaseOrder->po_number} menggunakan referensi harga supplier: ".$usedPriceListIds->implode(', ').'.',
            subject: $purchaseOrder,
            metadata: ['supplier_price_list_ids' => $usedPriceListIds->all()],
            riskLevel: ActivityLog::RISK_LOW,
        );
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
