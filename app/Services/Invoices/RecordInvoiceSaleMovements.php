<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockMovement;

class RecordInvoiceSaleMovements
{
    public function __construct(private readonly RecordStockMovement $recordStockMovement) {}

    /**
     * Deduct stock for each tracked item and collect low/negative-stock
     * alerts. Shared between CreateInvoiceDraft and UpdateInvoiceDraft.
     *
     * @param  iterable<InvoiceItem>  $items
     * @return list<array<string, mixed>>
     */
    public function handle(Invoice $invoice, iterable $items, ?int $actorId): array
    {
        $alerts = [];

        foreach ($items as $item) {
            $product = $item->product;

            if (! $product instanceof Product || ! $product->track_stock) {
                continue;
            }

            $movement = $this->recordStockMovement->record(
                product: $product,
                type: StockMovement::TYPE_SALE,
                quantity: -1 * (float) $item->quantity,
                referenceNumber: $invoice->invoice_number,
                notes: "Penjualan invoice {$invoice->invoice_number}",
                userId: $actorId,
            );

            $product->refresh();
            $stock = (float) ($product->stock ?? 0);
            $minimumStock = $product->minimumStockValue();

            if ($stock < 0 || $stock <= $minimumStock) {
                $alerts[] = [
                    'product_id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'stock' => $stock,
                    'minimum_stock' => $minimumStock,
                    'is_negative' => $stock < 0,
                    'movement_id' => $movement->getKey(),
                ];
            }
        }

        return $alerts;
    }
}
