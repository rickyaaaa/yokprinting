<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\InventoryDeficitSettlement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemCostLayer;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;

class FifoInventoryService
{
    public function __construct(private readonly RecordStockMovement $recordStockMovement) {}

    public function receive(
        GoodsReceiptItem $item,
        GoodsReceipt $goodsReceipt,
    ): ?InventoryBatch {
        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

        if (! $product->tracksInventory()) {
            return null;
        }

        // Incoming stock pays down any open FIFO deficit for this product
        // first (e.g. a prior sale oversold what was in stock). Only the
        // leftover, if any, becomes a new available batch - this is what
        // keeps SUM(inventory_batches.qty_remaining) reconciled against
        // product.stock instead of double-counting the same physical units.
        $qty = InventoryBatch::closeDeficitFor(
            $product->getKey(),
            round((float) $item->quantity_received, 4),
            [
                'goods_receipt_item_id' => $item->getKey(),
                'unit_cost' => (float) $item->unit_price,
                'source_type' => 'goods_receipt',
                'source_reference' => $goodsReceipt->receipt_number,
                'source_date' => $goodsReceipt->receipt_date,
            ],
        );

        return InventoryBatch::query()->create([
            'product_id' => $product->getKey(),
            'goods_receipt_item_id' => $item->getKey(),
            'purchase_date' => $goodsReceipt->receipt_date,
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'unit_cost' => $item->unit_price,
            'source_type' => 'goods_receipt',
            'source_reference' => $goodsReceipt->receipt_number,
        ]);
    }

    /**
     * Consume the oldest available layers and persist the cost snapshot.
     * The caller is expected to be inside the invoice transaction.
     */
    public function consume(Invoice $invoice, InvoiceItem $item, ?int $actorId = null): float
    {
        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

        if (! $item->tracksInventory()) {
            $hpp = round((float) $item->quantity * (float) $item->purchase_cost_snapshot, 2);
            $this->saveItemCost($item, $hpp);

            return $hpp;
        }

        $quantity = round((float) $item->quantity, 4);
        $this->consumeTrackedQuantity($invoice, $item, $product, $quantity);

        $this->recordStockMovement->record(
            product: $product,
            type: StockMovement::TYPE_SALE,
            quantity: -$quantity,
            referenceNumber: $invoice->invoice_number,
            notes: "Penjualan FIFO invoice {$invoice->invoice_number}",
            userId: $actorId,
        );

        return $this->refreshItemCost($item);
    }

    /** Add only the extra quantity introduced by an invoice edit. */
    public function consumeAdditional(Invoice $invoice, InvoiceItem $item, float $quantity, ?int $actorId = null): void
    {
        $quantity = round($quantity, 4);
        if ($quantity <= 0 || ! $item->tracksInventory()) {
            return;
        }

        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
        $this->consumeTrackedQuantity($invoice, $item, $product, $quantity);

        $this->recordStockMovement->record(
            product: $product,
            type: StockMovement::TYPE_SALE,
            quantity: -$quantity,
            referenceNumber: $invoice->invoice_number,
            notes: "Tambahan quantity invoice {$invoice->invoice_number}",
            userId: $actorId,
        );
    }

    private function consumeTrackedQuantity(Invoice $invoice, InvoiceItem $item, Product $product, float $quantity): void
    {
        $batches = $this->availableBatches($product);

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = min($remaining, (float) $batch->qty_remaining);
            $cost = round($consumed * (float) $batch->unit_cost, 2);

            $batch->forceFill([
                'qty_remaining' => round((float) $batch->qty_remaining - $consumed, 4),
            ])->save();

            $item->costLayers()->create([
                'inventory_batch_id' => $batch->getKey(),
                'qty_consumed' => $consumed,
                'unit_cost' => $batch->unit_cost,
                'total_cost' => $cost,
            ]);

            $remaining = round($remaining - $consumed, 4);
        }

        // Stock ran out before fully covering this sale - let the invoice
        // proceed with negative stock rather than blocking it, using the
        // best known unit cost for the shortfall (there's no real batch
        // left to draw an exact cost from). The shortfall itself IS tracked
        // as a deficit batch below so a future goods receipt / stock
        // adjustment closes it before creating fresh available FIFO stock -
        // this HPP snapshot never changes retroactively either way.
        if ($remaining > 0) {
            $fallbackCost = $this->bestKnownUnitCost($product);

            $deficitCost = round($remaining * $fallbackCost, 2);

            $deficitBatch = InventoryBatch::recordDeficitFor(
                $product->getKey(),
                $remaining,
                $fallbackCost,
                $invoice->invoice_number,
            );

            $item->costLayers()->create([
                'inventory_batch_id' => $deficitBatch->getKey(),
                'qty_consumed' => $remaining,
                'unit_cost' => $fallbackCost,
                'total_cost' => $deficitCost,
            ]);
        }
    }

    public function restoreInvoice(Invoice $invoice, ?int $actorId = null, ?string $reason = null): void
    {
        $items = $invoice->items()->with('costLayers')->orderBy('product_id')->orderBy('id')->get();

        // Every inventory workflow locks products first. Lock all products in
        // a stable order before touching batches/layers so two invoice
        // reversals with overlapping products cannot deadlock in opposite
        // item order.
        Product::query()
            ->whereIn('id', $items->pluck('product_id')->filter()->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

            if (! $product instanceof Product || ! $item->tracksInventory()) {
                continue;
            }

            $allLayers = $item->costLayers;
            $layers = $allLayers->whereNull('reversed_at');

            // A restored item may be encountered again by a retry or a
            // repeated lifecycle call. Once it has cost layers but none are
            // active, restoring it again must not create legacy stock.
            if ($allLayers->isNotEmpty() && $layers->isEmpty()) {
                continue;
            }

            if ($layers->isEmpty()) {
                $legacyUnitCost = (float) ($item->unit_hpp
                    ?: $item->purchase_cost_snapshot
                    ?: $product->average_purchase_cost
                    ?: $product->last_purchase_price
                    ?: $product->purchase_price
                    ?: 0);

                InventoryBatch::query()->create([
                    'product_id' => $product->getKey(),
                    'purchase_date' => Carbon::parse($invoice->issue_date ?? now())->toDateString(),
                    'qty_received' => $item->quantity,
                    'qty_remaining' => $item->quantity,
                    'unit_cost' => $legacyUnitCost,
                    'source_type' => 'legacy_return',
                    'source_reference' => $invoice->invoice_number,
                ]);

                $this->recordStockMovement->record(
                    product: $product,
                    type: StockMovement::TYPE_ADJUSTMENT,
                    quantity: (float) $item->quantity,
                    referenceNumber: $invoice->invoice_number,
                    notes: $reason ?? "Pengembalian stok invoice {$invoice->invoice_number}",
                    userId: $actorId,
                    syncFifo: false,
                );

                continue;
            }

            $this->restoreItemQuantity(
                $invoice,
                $item,
                (float) $layers->sum('qty_consumed'),
                $actorId,
                $reason,
            );
        }
    }

    /** Restore only the quantity removed by an invoice edit or cancellation. */
    public function restoreItemQuantity(
        Invoice $invoice,
        InvoiceItem $item,
        float $quantity,
        ?int $actorId = null,
        ?string $reason = null,
    ): void {
        $quantity = round($quantity, 4);
        if ($quantity <= 0 || ! $item->tracksInventory()) {
            return;
        }

        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
        $layers = $item->costLayers()
            ->whereNull('reversed_at')
            ->orderByDesc('id')
            ->get();
        $remaining = $quantity;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $restored = min($remaining, (float) $layer->qty_consumed);
            $this->restoreLayerQuantity($product, $layer, $restored, $invoice);
            $remaining = round($remaining - $restored, 4);
        }

        // Legacy invoices can have a net sale movement without cost-layer
        // rows. Keep their existing explicit fallback behavior, but label the
        // resulting layer so the estimated provenance is visible in audit.
        if ($remaining > 0) {
            $legacyUnitCost = (float) ($item->unit_hpp
                ?: $item->purchase_cost_snapshot
                ?: $product->purchaseCostFallback());

            InventoryBatch::query()->create([
                'product_id' => $product->getKey(),
                'purchase_date' => Carbon::parse($invoice->issue_date ?? now())->toDateString(),
                'qty_received' => $remaining,
                'qty_remaining' => $remaining,
                'unit_cost' => $legacyUnitCost,
                'source_type' => 'legacy_return',
                'source_reference' => $invoice->invoice_number,
            ]);
        }

        $this->recordStockMovement->record(
            product: $product,
            type: StockMovement::TYPE_ADJUSTMENT,
            quantity: $quantity,
            referenceNumber: $invoice->invoice_number,
            notes: $reason ?? "Pengembalian layer FIFO invoice {$invoice->invoice_number}",
            userId: $actorId,
            syncFifo: false,
        );
    }

    private function restoreLayerQuantity(
        Product $product,
        InvoiceItemCostLayer $layer,
        float $quantity,
        Invoice $invoice,
    ): void {
        $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($layer->inventory_batch_id);
        $layer = InvoiceItemCostLayer::query()->lockForUpdate()->findOrFail($layer->getKey());
        $settled = 0.0;

        if ($batch->source_type === 'deficit') {
            $settled = round((float) InventoryDeficitSettlement::query()
                ->where('invoice_item_cost_layer_id', $layer->getKey())
                ->whereNull('reversed_at')
                ->sum('quantity'), 4);
            $unresolved = max(0, round((float) $layer->qty_consumed - $settled, 4));
            $unresolvedToRestore = min($quantity, $unresolved);

            if ($unresolvedToRestore > 0) {
                $batch->increment('qty_remaining', $unresolvedToRestore);
            }

            $settled = round($quantity - $unresolvedToRestore, 4);
        } else {
            $batch->increment('qty_remaining', $quantity);
        }

        $reversedLayer = $this->splitOrReverseCostLayer($layer, $quantity);

        if ($settled > 0) {
            $this->restoreSettledQuantity($product, $layer, $reversedLayer, $settled, $invoice);
        }
    }

    private function splitOrReverseCostLayer(InvoiceItemCostLayer $layer, float $quantity): InvoiceItemCostLayer
    {
        $originalQuantity = round((float) $layer->qty_consumed, 4);
        if ($quantity >= $originalQuantity) {
            $layer->forceFill(['reversed_at' => now()])->save();

            return $layer;
        }

        $remaining = round($originalQuantity - $quantity, 4);
        $layer->forceFill([
            'qty_consumed' => $remaining,
            'total_cost' => round($remaining * (float) $layer->unit_cost, 2),
        ])->save();

        return InvoiceItemCostLayer::query()->create([
            'invoice_item_id' => $layer->invoice_item_id,
            'inventory_batch_id' => $layer->inventory_batch_id,
            'qty_consumed' => $quantity,
            'unit_cost' => $layer->unit_cost,
            'total_cost' => round($quantity * (float) $layer->unit_cost, 2),
            'reversed_at' => now(),
        ]);
    }

    private function restoreSettledQuantity(
        Product $product,
        InvoiceItemCostLayer $activeLayer,
        InvoiceItemCostLayer $reversedLayer,
        float $quantity,
        Invoice $invoice,
    ): void {
        $remaining = $quantity;
        $settlements = InventoryDeficitSettlement::query()
            ->where('invoice_item_cost_layer_id', $activeLayer->getKey())
            ->whereNull('reversed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($settlements as $settlement) {
            if ($remaining <= 0) {
                break;
            }

            $restored = min($remaining, (float) $settlement->quantity);
            $compensation = InventoryBatch::query()->create([
                'product_id' => $product->getKey(),
                // Keep the exact receipt link on the settlement row. A
                // GoodsReceiptItem owns one original FIFO batch; attaching a
                // later compensation batch to it as well would violate that
                // has-one contract and make receipt inspection ambiguous.
                'goods_receipt_item_id' => null,
                'purchase_date' => $settlement->source_date,
                'qty_received' => $restored,
                'qty_remaining' => $restored,
                'unit_cost' => $settlement->unit_cost,
                'source_type' => 'deficit_compensation',
                'source_reference' => $settlement->source_reference ?? $invoice->invoice_number,
            ]);

            if ($restored >= (float) $settlement->quantity) {
                $settlement->forceFill([
                    'invoice_item_cost_layer_id' => $reversedLayer->getKey(),
                    'compensation_batch_id' => $compensation->getKey(),
                    'reversed_at' => now(),
                ])->save();
            } else {
                $settlement->decrement('quantity', $restored);
                InventoryDeficitSettlement::query()->create([
                    'inventory_batch_id' => $settlement->inventory_batch_id,
                    'invoice_item_cost_layer_id' => $reversedLayer->getKey(),
                    'goods_receipt_item_id' => $settlement->goods_receipt_item_id,
                    'compensation_batch_id' => $compensation->getKey(),
                    'quantity' => $restored,
                    'unit_cost' => $settlement->unit_cost,
                    'source_type' => $settlement->source_type,
                    'source_reference' => $settlement->source_reference,
                    'source_date' => $settlement->source_date,
                    'reversed_at' => now(),
                ]);
            }

            $remaining = round($remaining - $restored, 4);
        }
    }

    /**
     * Whether an invoice has already crossed the first-verified-payment
     * inventory boundary. New unpaid drafts have neither cost layers nor a
     * sale movement, so edit/cancel flows must leave their stock untouched.
     */
    public function hasConsumedInventory(Invoice $invoice): bool
    {
        if ($invoice->items()
            ->whereHas('costLayers', fn ($query) => $query->whereNull('reversed_at'))
            ->exists()) {
            return true;
        }

        // Legacy invoices may predate FIFO cost layers, so fall back to their
        // net inventory ledger impact. A completed restore adds an equal
        // positive adjustment under the same reference and therefore returns
        // the net impact to zero, allowing a later first DP to consume again.
        $netMovement = (float) StockMovement::query()
            ->where('reference_number', $invoice->invoice_number)
            ->whereIn('type', [StockMovement::TYPE_SALE, StockMovement::TYPE_ADJUSTMENT])
            ->sum('quantity');

        return $netMovement < 0;
    }

    public function availableQuantity(int $productId): float
    {
        return round((float) InventoryBatch::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->sum('qty_remaining'), 4);
    }

    private function availableBatches(Product $product)
    {
        $query = InventoryBatch::query()
            ->where('product_id', $product->getKey())
            ->where('qty_remaining', '>', 0)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->lockForUpdate();

        $batches = $query->get();

        if ($batches->isEmpty() && (float) ($product->stock ?? 0) > 0) {
            $unitCost = $this->bestKnownUnitCost($product);

            $opening = InventoryBatch::query()->create([
                'product_id' => $product->getKey(),
                'purchase_date' => Carbon::parse($product->created_at ?? now())->toDateString(),
                'qty_received' => $product->stock,
                'qty_remaining' => $product->stock,
                'unit_cost' => $unitCost,
                'source_type' => 'opening_balance',
                'source_reference' => 'FIFO-LEGACY-FALLBACK',
            ]);

            return collect([$opening]);
        }

        return $batches;
    }

    public function refreshItemCost(InvoiceItem $item): float
    {
        $hpp = round((float) $item->costLayers()->whereNull('reversed_at')->sum('total_cost'), 2);
        $this->saveItemCost($item, $hpp);

        return $hpp;
    }

    private function saveItemCost(InvoiceItem $item, float $hpp): void
    {
        $quantity = (float) $item->quantity;
        $item->forceFill([
            'hpp_total' => $hpp,
            'unit_hpp' => $quantity > 0 ? round($hpp / $quantity, 2) : 0,
            'purchase_cost_snapshot' => $quantity > 0 ? round($hpp / $quantity, 2) : 0,
        ])->save();
    }

    private function bestKnownUnitCost(Product $product): float
    {
        foreach ([$product->average_purchase_cost, $product->last_purchase_price, $product->purchase_price] as $candidate) {
            if ($candidate !== null && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return 0.0;
    }
}
