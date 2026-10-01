<?php

namespace App\Services\Reports;

use App\Models\ActivityLog;
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
        $excludedReferences = $this->cancelledReferences();

        $products = Product::query()
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->when(! $productId, fn ($query) => $query->whereHas('stockMovements', fn ($query) => $query->where('created_at', '<=', $end)))
            ->with([
                // Keep the complete ledger in memory. The period view is
                // derived from it, while reconciliation must also compare
                // the current Product.stock with every active movement.
                'stockMovements' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
                'inventoryBatches' => fn ($query) => $query->where('qty_remaining', '>', 0),
            ])
            ->orderBy('name')
            ->get();

        $rows = $products
            ->filter(fn (Product $product): bool => $productId || $this->activeMovements($product->stockMovements, $excludedReferences)->isNotEmpty())
            ->map(fn (Product $product): array => $this->summaryRow($product, $start, $end, $excludedReferences))
            ->values();
        $selectedProduct = $productId ? $products->firstWhere('id', $productId) : null;
        $detail = $selectedProduct
            ? $this->detail($selectedProduct, $start, $end, $excludedReferences)
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
    private function summaryRow(Product $product, CarbonImmutable $start, CarbonImmutable $end, Collection $excludedReferences): array
    {
        /** @var Collection<int, StockMovement> $movements */
        $movements = $this->activeMovements($product->stockMovements, $excludedReferences);
        $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        $adjustmentTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_STOCK_OPNAME];
        $regularMovements = $period->whereNotIn('type', $adjustmentTypes);
        $incoming = (float) $regularMovements->where('quantity', '>', 0)->sum('quantity');
        $outgoing = abs((float) $regularMovements->where('quantity', '<', 0)->sum('quantity'));
        $adjustments = (float) $period->whereIn('type', $adjustmentTypes)->sum('quantity');
        $opening = $this->openingBalance($product, $start, $excludedReferences);
        $closing = (float) ($opening + $period->sum('quantity'));
        $reconciliation = $this->reconciliation($product, $movements, $end, $excludedReferences);

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
            'ledger_current_stock' => $reconciliation['ledger_stock'],
            'stock_difference' => $reconciliation['difference'],
            'reconciliation_status' => $reconciliation['reconciliation_status'],
            'fifo_inventory_value' => round((float) $product->inventoryBatches->sum(
                fn ($batch): float => (float) $batch->qty_remaining * (float) $batch->unit_cost,
            ), 2),
        ];
    }

    /**
     * @return array{product: array<string, mixed>, mutations: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function detail(Product $product, CarbonImmutable $start, CarbonImmutable $end, Collection $excludedReferences): array
    {
        /** @var Collection<int, StockMovement> $movements */
        $movements = $this->activeMovements($product->stockMovements, $excludedReferences);
        $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end)->values();
        $opening = $this->openingBalance($product, $start, $excludedReferences);
        $contexts = $this->referenceContexts($period);
        $legacyReturns = $period->filter(fn (StockMovement $movement): bool => str_starts_with((string) $movement->notes, 'Pengembalian layer FIFO invoice '));
        $editLogs = ActivityLog::query()
            ->where('module', 'invoice')
            ->whereIn('action', ['draft_updated', 'updated_after_issuance'])
            ->whereIn('subject_id', Invoice::query()->whereIn('invoice_number', $legacyReturns->pluck('reference_number')->filter()->unique())->pluck('id'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id');
        $invoiceIds = Invoice::query()->whereIn('invoice_number', $legacyReturns->pluck('reference_number')->filter()->unique())
            ->pluck('id', 'invoice_number');
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
            $context = match ($movement->type) {
                StockMovement::TYPE_SALE => $contexts['sale'][$movement->reference_number] ?? null,
                StockMovement::TYPE_PURCHASE => $contexts['purchase'][$movement->reference_number] ?? null,
                default => null,
            };
            $party = $context['party'] ?? null;
            $description = $context['description']
                ?? $movement->notes
                ?? $this->typeLabel($movement->type);
            if ($legacyReturns->contains('id', $movement->id)) {
                $invoiceId = $invoiceIds[$movement->reference_number] ?? null;
                $description = $this->legacyReturnDescription($movement, $editLogs[$invoiceId] ?? collect()) ?? $description;
            }

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
            'adjustments' => round((float) $period->whereIn('type', [
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_STOCK_OPNAME,
            ])->sum('quantity'), 4),
            'closing_balance' => $balance,
            ...$this->reconciliation($product, $movements, $end, $excludedReferences),
        ];

        return [
            'product' => [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
            ],
            // Keep the balance calculated in chronological order, but show
            // the newest transaction first as requested for the report.
            'mutations' => collect($rows)->reverse()->values()->all(),
            'summary' => $summary,
        ];
    }

    /** @param Collection<int, ActivityLog> $logs */
    private function legacyReturnDescription(StockMovement $movement, Collection $logs): ?string
    {
        foreach ($logs as $log) {
            if (! $movement->created_at || ! $log->occurred_at
                || $log->occurred_at->lt($movement->created_at)
                || $log->occurred_at->gt($movement->created_at->copy()->addMinute())) {
                continue;
            }

            $before = collect($log->metadata['items_before'] ?? []);
            $after = collect($log->metadata['items_after'] ?? []);
            if (! $before->contains(fn (array $item): bool => (int) ($item['product_id'] ?? 0) === (int) $movement->product_id)
                || $before->map(fn (array $item): string => ($item['product_id'] ?? '').':'.($item['quantity'] ?? ''))->sort()->values()->all()
                    === $after->map(fn (array $item): string => ($item['product_id'] ?? '').':'.($item['quantity'] ?? ''))->sort()->values()->all()) {
                continue;
            }

            $format = fn (Collection $items): string => $items->map(fn (array $item): string => (string) ($item['sku'] ?? Product::query()->find($item['product_id'] ?? null)?->sku ?? '#'.($item['product_id'] ?? '?')))->implode(', ');

            return "Pengembalian FIFO karena perubahan item invoice {$movement->reference_number} dari {$format($before)} ke {$format($after)}";
        }

        return null;
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

        $contexts = [
            'sale' => [],
            'purchase' => [],
        ];
        Invoice::query()
            ->with('customer:id,name')
            ->whereIn('invoice_number', $saleReferences)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get()
            ->each(function (Invoice $invoice) use (&$contexts): void {
                $customer = $invoice->customer?->name;
                $contexts['sale'][$invoice->invoice_number] = [
                    'party' => $customer,
                    'party_type' => 'customer',
                    'description' => $customer ? "Penjualan ke {$customer}" : 'Penjualan customer',
                ];
            });

        GoodsReceipt::query()
            ->with('purchaseOrder.supplier:id,name')
            ->whereIn('receipt_number', $purchaseReferences)
            ->where('status', '!=', GoodsReceipt::STATUS_CANCELLED)
            ->get()
            ->each(function (GoodsReceipt $receipt) use (&$contexts): void {
                $supplier = $receipt->purchaseOrder?->supplier?->name;
                $contexts['purchase'][$receipt->receipt_number] = [
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

    private function openingBalance(Product $product, CarbonImmutable $start, Collection $excludedReferences): float
    {
        $movements = $this->activeMovements($product->stockMovements, $excludedReferences);
        $first = $movements->sortBy([['created_at', 'asc'], ['id', 'asc']])->first();
        $baseline = $first ? (float) $first->stock_before : (float) ($product->stock ?? 0);

        return round($baseline + (float) $movements->where('created_at', '<', $start)->sum('quantity'), 4);
    }

    /**
     * Compare the persisted product stock with the active stock ledger.
     * A product without any movement has no historical ledger baseline, so it
     * is intentionally reported as not applicable rather than falsely flagged.
     *
     * @param  Collection<int, StockMovement>  $movements
     * @return array{ledger_stock: float|null, product_stock: float|null, difference: float|null, reconciliation_status: string}
     */
    private function reconciliation(Product $product, Collection $movements, CarbonImmutable $end, Collection $excludedReferences): array
    {
        if (! $product->track_stock || $product->stock === null) {
            return [
                'ledger_stock' => null,
                'product_stock' => $product->stock === null ? null : (float) $product->stock,
                'difference' => null,
                'reconciliation_status' => 'not_applicable',
            ];
        }

        $active = $this->activeMovements($movements, $excludedReferences);
        if ($active->isEmpty()) {
            return [
                'ledger_stock' => null,
                'product_stock' => (float) $product->stock,
                'difference' => 0.0,
                'reconciliation_status' => 'not_applicable',
            ];
        }

        $first = $active->sortBy([['created_at', 'asc'], ['id', 'asc']])->first();
        $ledgerStock = round((float) $first->stock_before + (float) $active->sum('quantity'), 4);
        $difference = round((float) $product->stock - $ledgerStock, 4);

        // A historical period can legitimately end before later movements,
        // so only compare to the live product balance when the selected end
        // date reaches today.
        $status = $end->lt(CarbonImmutable::now()->startOfDay())
            ? 'historical_period'
            : (abs($difference) < 0.0001 ? 'balanced' : 'needs_reconciliation');

        return [
            'ledger_stock' => $ledgerStock,
            'product_stock' => (float) $product->stock,
            'difference' => $difference,
            'reconciliation_status' => $status,
        ];
    }

    /**
     * @return Collection<string, true>
     */
    private function cancelledReferences(): Collection
    {
        return Invoice::query()
            ->where('status', Invoice::STATUS_CANCELLED)
            ->pluck('invoice_number')
            ->merge(GoodsReceipt::query()->where('status', GoodsReceipt::STATUS_CANCELLED)->pluck('receipt_number'))
            ->filter()
            ->unique()
            ->flip()
            ->map(fn (): bool => true);
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @param  Collection<string, true>  $excludedReferences
     * @return Collection<int, StockMovement>
     */
    private function activeMovements(Collection $movements, Collection $excludedReferences): Collection
    {
        return $movements
            ->reject(fn (StockMovement $movement): bool => $movement->reference_number
                && $excludedReferences->has($movement->reference_number))
            ->values();
    }
}
