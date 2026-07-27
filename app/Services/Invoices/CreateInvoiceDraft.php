<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateInvoiceDraft
{
    public function __construct(
        private readonly CalculateInvoiceTotals $calculateInvoiceTotals,
        private readonly GenerateInvoiceNumber $generateInvoiceNumber,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    /**
     * Persist a new invoice draft and all line items atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?int $creatorId = null): Invoice
    {
        return DB::transaction(function () use ($data, $creatorId): Invoice {
            $items = $this->snapshotItemsFromProducts($data['items']);
            $totals = $this->calculateInvoiceTotals->calculate(
                $items,
                $data['discount'],
                $data['tax'],
                $data['shipping_type'] ?? Invoice::SHIPPING_NONE,
                $data['shipping_cost'] ?? 0,
            );

            $invoice = Invoice::query()->create([
                'customer_id' => $data['customer_id'],
                'created_by' => $creatorId,
                'invoice_number' => $this->generateInvoiceNumber->generate(
                    CarbonImmutable::parse($data['issue_date']),
                ),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => Invoice::STATUS_DRAFT,
                'payment_status' => Invoice::PAYMENT_UNPAID,
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'shipping_type' => $totals['shipping_type'],
                'shipping_cost' => $totals['shipping_cost'],
                'order_process_status' => $data['order_process_status'] ?? Invoice::ORDER_PROCESS_DRAFT,
                'total_hpp' => $totals['total_hpp'],
                'gross_profit' => $totals['gross_profit'],
                'production_status' => $data['production_status'] ?? Invoice::PRODUCTION_DRAFT,
                'dp_required_percent' => $data['dp_required_percent'] ?? 50,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'design_notes' => $data['design_notes'] ?? null,
                'mockup_url' => $data['mockup_url'] ?? null,
                'template' => $data['template'] ?? 'default',
                'theme_color' => $data['theme_color'] ?? null,
                'metadata' => [
                    'source' => 'invoice-draft-api',
                    'business_vertical' => 'sablon-cup-fnb',
                ],
            ]);

            $createdItems = $invoice->items()->createMany($totals['items']);
            $stockAlerts = $this->recordSaleMovements($invoice, $createdItems, $creatorId);

            if ($stockAlerts !== []) {
                $invoice->forceFill([
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'inventory_alerts' => $stockAlerts,
                    ]),
                ])->save();
            }

            return $invoice->load('items');
        });
    }

    /**
     * Snapshot mutable product procurement data at invoice creation time.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function snapshotItemsFromProducts(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products): array {
                $product = $products->get($item['product_id'] ?? null);

                if ($product instanceof Product) {
                    $item['product_name'] = $item['product_name'] ?? $product->name;
                    $item['sku'] = $item['sku'] ?? $product->sku;
                    $item['cup_size'] = $item['cup_size'] ?? $product->cup_size;
                    $item['cup_model'] = $item['cup_model'] ?? $product->cup_model;
                    $item['grammage'] = $item['grammage'] ?? $product->grammage;
                    $item['screen_printing_color'] = $item['screen_printing_color'] ?? $product->screen_printing_color;
                    $item['moq_quantity'] = $product->minimum_order_qty ?: $product->moq_quantity;
                    $item['order_increment'] = $product->package_conversion ?: $product->order_increment;
                    $item['packaging_unit'] = $product->unit ?: Product::UNIT_PCS;
                    $item['purchase_cost_snapshot'] = $product->purchase_price;
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<\App\Models\InvoiceItem>  $items
     * @return list<array<string, mixed>>
     */
    private function recordSaleMovements(Invoice $invoice, iterable $items, ?int $creatorId): array
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
                userId: $creatorId,
            );

            $product->refresh();
            $stock = (float) ($product->stock ?? 0);
            $minimumStock = (float) ($product->minimum_stock ?? 0);

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
