<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FifoInventoryService
{
    public function __construct(private readonly RecordStockMovement $recordStockMovement) {}

    public function receive(
        GoodsReceiptItem $item,
        GoodsReceipt $goodsReceipt,
    ): ?InventoryBatch {
        $product = Product::query()->findOrFail($item->product_id);

        if (! $product->track_stock) {
            return null;
        }

        return InventoryBatch::query()->create([
            'product_id' => $product->getKey(),
            'goods_receipt_item_id' => $item->getKey(),
            'purchase_date' => $goodsReceipt->receipt_date,
            'qty_received' => $item->quantity_received,
            'qty_remaining' => $item->quantity_received,
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

        if (! $product->track_stock) {
            $hpp = round((float) $item->quantity * (float) $item->purchase_cost_snapshot, 2);
            $this->saveItemCost($item, $hpp);

            return $hpp;
        }

        $quantity = round((float) $item->quantity, 4);
        $batches = $this->availableBatches($product);
        $available = round((float) $batches->sum('qty_remaining'), 4);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                "items.{$item->sort_order}.quantity" => "Stok {$product->name} tidak mencukupi. Stok tersedia: {$this->formatQuantity($available)}.",
            ]);
        }

        $remaining = $quantity;
        $hpp = 0.0;

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
            $hpp += $cost;
        }

        $this->recordStockMovement->record(
            product: $product,
            type: StockMovement::TYPE_SALE,
            quantity: -$quantity,
            referenceNumber: $invoice->invoice_number,
            notes: "Penjualan FIFO invoice {$invoice->invoice_number}",
            userId: $actorId,
        );

        $hpp = round($hpp, 2);
        $this->saveItemCost($item, $hpp);

        return $hpp;
    }

    public function restoreInvoice(Invoice $invoice, ?int $actorId = null): void
    {
        foreach ($invoice->items()->with('costLayers')->get() as $item) {
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

            if (! $product instanceof Product || ! $product->track_stock) {
                continue;
            }

            $layers = $item->costLayers->whereNull('reversed_at');

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
                    notes: "Pengembalian stok invoice {$invoice->invoice_number}",
                    userId: $actorId,
                    syncFifo: false,
                );

                continue;
            }

            foreach ($layers as $layer) {
                $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($layer->inventory_batch_id);
                $batch->increment('qty_remaining', (float) $layer->qty_consumed);
                $layer->forceFill(['reversed_at' => now()])->save();
            }

            $this->recordStockMovement->record(
                product: $product,
                type: StockMovement::TYPE_ADJUSTMENT,
                quantity: (float) $item->quantity,
                referenceNumber: $invoice->invoice_number,
                notes: "Pengembalian layer FIFO invoice {$invoice->invoice_number}",
                userId: $actorId,
                syncFifo: false,
            );
        }
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
            $unitCost = (float) ($product->average_purchase_cost
                ?? $product->last_purchase_price
                ?? $product->purchase_price
                ?? 0);

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

    private function saveItemCost(InvoiceItem $item, float $hpp): void
    {
        $quantity = (float) $item->quantity;
        $item->forceFill([
            'hpp_total' => $hpp,
            'unit_hpp' => $quantity > 0 ? round($hpp / $quantity, 2) : 0,
            'purchase_cost_snapshot' => $quantity > 0 ? round($hpp / $quantity, 2) : 0,
        ])->save();
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',');
    }
}
