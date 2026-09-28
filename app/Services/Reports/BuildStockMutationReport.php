<?php

namespace App\Services\Reports;

use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildStockMutationReport
{
    /**
     * Build both the product summary and, when requested, the transaction
     * detail used by the per-product mutation report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now())->endOfDay();
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;

        $products = Product::query()
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->when(! $productId, fn ($query) => $query->whereHas('stockMovements', fn ($query) => $query->where('created_at', '<=', $end)))
            ->with([
                'stockMovements' => fn ($query) => $query->where('created_at', '<=', $end)->orderBy('created_at')->orderBy('id'),
                'inventoryBatches' => fn ($query) => $query->where('qty_remaining', '>', 0),
            ])
            ->orderBy('name')
            ->get();

        $rows = $products->map(fn (Product $product): array => $this->summaryRow($product, $start, $end))->values();
        $selectedProduct = $productId ? $products->firstWhere('id', $productId) : null;
        $detail = $selectedProduct
            ? $this->detail($selectedProduct, $start, $end)
            : null;

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'products_count' => $rows->count(),
                'total_incoming' => round((float) $rows->sum('incoming_quantity'), 4),
                'total_outgoing' => round((float) $rows->sum('outgoing_quantity'), 4),
                'total_adjustments' => round((float) $rows->sum('adjustments'), 4),
            ],
            'products' => $rows,
            'product' => $detail['product'] ?? null,
            'mutations' => $detail['mutations'] ?? [],
            'detail_summary' => $detail['summary'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryRow(Product $product, CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var Collection<int, StockMovement> $movements */
        $movements = $product->stockMovements;
        $before = $movements->where('created_at', '<', $start);
        $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        $adjustmentTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_STOCK_OPNAME];
        $regularMovements = $period->whereNotIn('type', $adjustmentTypes);
        $incoming = (float) $regularMovements->where('quantity', '>', 0)->sum('quantity');
        $outgoing = abs((float) $regularMovements->where('quantity', '<', 0)->sum('quantity'));
        $adjustments = (float) $period->whereIn('type', $adjustmentTypes)->sum('quantity');
        $opening = $this->openingBalance($product, $start);
        $closing = (float) ($opening + $period->sum('quantity'));

        return [
            'product_id' => $product->getKey(),
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'unit' => $product->unit,
            'opening_balance' => round($opening, 4),
            'incoming_quantity' => round($incoming, 4),
            'outgoing_quantity' => round($outgoing, 4),
            'adjustments' => round($adjustments, 4),
            'closing_balance' => round($closing, 4),
            'current_stock' => $product->stock === null ? null : (float) $product->stock,
            'fifo_inventory_value' => round((float) $product->inventoryBatches->sum(
                fn ($batch): float => (float) $batch->qty_remaining * (float) $batch->unit_cost,
            ), 2),
        ];
    }

    /**
     * @return array{product: array<string, mixed>, mutations: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function detail(Product $product, CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var Collection<int, StockMovement> $movements */
        $movements = $product->stockMovements;
        $before = $movements->where('created_at', '<', $start);
        $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end)->values();
        $opening = $this->openingBalance($product, $start);
        $contexts = $this->referenceContexts($period);
        $balance = $opening;
        $rows = [[
            'id' => 'opening-'.$product->getKey(),
            'document_number' => null,
            'date' => $start->subDay()->toDateString(),
            'description' => 'Saldo awal per '.$start->subDay()->format('d/m/Y'),
            'party' => null,
            'party_type' => null,
            'type' => StockMovement::TYPE_OPENING_BALANCE,
            'incoming' => 0,
            'outgoing' => 0,
            'balance' => $opening,
        ]];

        foreach ($period as $movement) {
            $quantity = (float) $movement->quantity;
            $balance = round($balance + $quantity, 4);
            $context = $contexts[$movement->reference_number] ?? null;
            $party = $context['party'] ?? null;
            $description = $context['description']
                ?? $movement->notes
                ?? $this->typeLabel($movement->type);

            $rows[] = [
                'id' => $movement->getKey(),
                'document_number' => $movement->reference_number,
                'date' => $movement->created_at?->toDateString(),
                'description' => $description,
                'party' => $party,
                'party_type' => $context['party_type'] ?? null,
                'type' => $movement->type,
                'incoming' => $quantity > 0 ? $quantity : 0,
                'outgoing' => $quantity < 0 ? abs($quantity) : 0,
                'balance' => $balance,
            ];
        }

        $summary = [
            'opening_balance' => $opening,
            'incoming' => round((float) $period->where('quantity', '>', 0)->sum('quantity'), 4),
            'outgoing' => round(abs((float) $period->where('quantity', '<', 0)->sum('quantity')), 4),
            'closing_balance' => $balance,
        ];

        return [
            'product' => [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
            ],
            'mutations' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return array<string, array{party: string|null, party_type: string, description: string}>
     */
    private function referenceContexts(Collection $movements): array
    {
        $saleReferences = $movements
            ->where('type', StockMovement::TYPE_SALE)
            ->pluck('reference_number')
            ->filter()
            ->unique()
            ->values();
        $purchaseReferences = $movements
            ->where('type', StockMovement::TYPE_PURCHASE)
            ->pluck('reference_number')
            ->filter()
            ->unique()
            ->values();

        $contexts = [];
        Invoice::query()
            ->with('customer:id,name')
            ->whereIn('invoice_number', $saleReferences)
            ->get()
            ->each(function (Invoice $invoice) use (&$contexts): void {
                $customer = $invoice->customer?->name;
                $contexts[$invoice->invoice_number] = [
                    'party' => $customer,
                    'party_type' => 'customer',
                    'description' => $customer ? "Penjualan ke {$customer}" : 'Penjualan customer',
                ];
            });

        GoodsReceipt::query()
            ->with('purchaseOrder.supplier:id,name')
            ->whereIn('receipt_number', $purchaseReferences)
            ->get()
            ->each(function (GoodsReceipt $receipt) use (&$contexts): void {
                $supplier = $receipt->purchaseOrder?->supplier?->name;
                $contexts[$receipt->receipt_number] = [
                    'party' => $supplier,
                    'party_type' => 'supplier',
                    'description' => $supplier ? "Pembelian dari {$supplier}" : 'Penerimaan pembelian',
                ];
            });

        return $contexts;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            StockMovement::TYPE_ADJUSTMENT => 'Penyesuaian stok',
            StockMovement::TYPE_STOCK_OPNAME => 'Stok opname',
            StockMovement::TYPE_RETURN => 'Retur barang',
            StockMovement::TYPE_PURCHASE => 'Barang masuk',
            StockMovement::TYPE_SALE => 'Barang keluar',
            default => 'Mutasi stok',
        };
    }

    private function openingBalance(Product $product, CarbonImmutable $start): float
    {
        $first = $product->stockMovements()->orderBy('created_at')->orderBy('id')->first();
        $baseline = $first ? (float) $first->stock_before : (float) ($product->stock ?? 0);

        return round($baseline + (float) $product->stockMovements->where('created_at', '<', $start)->sum('quantity'), 4);
    }
}
