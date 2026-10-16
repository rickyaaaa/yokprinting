<?php

namespace App\Services\Invoices;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceDraft
{
    public function __construct(
        private readonly CalculateInvoiceTotals $calculateInvoiceTotals,
        private readonly SnapshotInvoiceItems $snapshotInvoiceItems,
        private readonly RecordInvoiceSaleMovements $recordInvoiceSaleMovements,
        private readonly FifoInventoryService $fifoInventory,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Replace an invoice's header and line items - allowed at any point in
     * its active lifecycle (draft, sent, paid, and any production_status) as
     * long as it isn't cancelled; see Invoice::isEditable(). Editing never touches
     * `status`/`sent_at` (an already-sent invoice stays sent) or
     * `production_status`/`order_process_status`/mockup/template/theme
     * unless the caller explicitly sent a new value for them.
     *
     * Sale stock movements are deducted at the first verified payment. An
     * unpaid invoice has no inventory impact. Once paid, same-product quantity
     * edits adjust only their delta; removed products are compensated and new
     * products are consumed. Deficit settlements preserve the receipt or
     * adjustment cost needed to make those compensations auditable.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $actorId): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->lockForUpdate()->with('items')->findOrFail($invoice->getKey());

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'Invoice yang sudah dibatalkan tidak bisa diedit.',
                ]);
            }

            $items = $this->snapshotInvoiceItems->handle($data['items']);
            $isFreeShipping = (bool) ($data['is_free_shipping'] ?? false);
            $shippingCost = $data['shipping_cost'] ?? 0;
            $shippingType = $isFreeShipping
                ? Invoice::SHIPPING_COMPANY_FREE_SHIPPING
                : (((float) $shippingCost > 0) ? Invoice::SHIPPING_PAID_BY_CUSTOMER : Invoice::SHIPPING_NONE);

            $totals = $this->calculateInvoiceTotals->calculate(
                $items,
                $data['discount'],
                $data['tax'],
                $data['shipping_type'] ?? $shippingType,
                $shippingCost,
            );

            // Fail fast, before touching FIFO/stock/items at all: an
            // invoice can never be edited into a state where it owes less
            // than what's already been verified as received. lockForUpdate
            // here (same invoice-row lock RecordInvoicePayment takes before
            // writing a payment) means a payment being recorded concurrently
            // either finishes first and is included in this sum, or blocks
            // until this transaction commits/rolls back - never both half-applied.
            $verifiedPaid = round(
                (float) $locked->payments()->verified()->lockForUpdate()->sum('amount'),
                2,
                PHP_ROUND_HALF_UP,
            );

            if ($totals['total_amount'] < $verifiedPaid) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Total invoice baru tidak boleh lebih kecil dari pembayaran yang sudah diterima.',
                ]);
            }

            $totalBefore = (float) $locked->total_amount;
            $hppBefore = (float) $locked->total_hpp;
            $statusBefore = $locked->status;
            $productionStatusBefore = $locked->production_status;
            $itemsBefore = $locked->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => (float) $item->quantity,
                'hpp_total' => (float) $item->hpp_total,
            ])->values()->all();
            $itemsChanged = $this->inventoryIdentity($itemsBefore) !== $this->inventoryIdentity($totals['items']);

            $hasConsumedInventory = $this->fifoInventory->hasConsumedInventory($locked);

            $locked->update([
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
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
                'is_free_shipping' => $isFreeShipping,
                // Not exposed as a field on the invoice create/edit form, so
                // never take these from $data on update - only the
                // dedicated flows that actually manage them (mockup upload,
                // order-process tracking) should ever change them. status,
                // sent_at, and payment_status are likewise deliberately
                // absent from this array - editing must never revert an
                // already-sent invoice to draft or lose its delivery/payment
                // history; payment_status is recalculated explicitly below.
                'order_process_status' => $locked->order_process_status,
                'mockup_url' => $locked->mockup_url,
                'template' => $locked->template,
                'theme_color' => $locked->theme_color,
                'total_hpp' => $totals['total_hpp'],
                'gross_profit' => $totals['gross_profit'],
                'production_status' => $data['production_status'] ?? $locked->production_status,
                'dp_required_percent' => array_key_exists('dp_required_percent', $data)
                    ? ($data['dp_required_percent'] ?? 50)
                    : $locked->dp_required_percent,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'design_notes' => $data['design_notes'] ?? null,
            ]);

            if ($verifiedPaid > 0 && $itemsChanged && $hasConsumedInventory) {
                // Reconcile product-by-product and apply only the quantity
                // delta. This preserves historical cost layers for unchanged
                // quantities and avoids temporarily reversing an entire sale
                // merely because one line changed.
                $currentItems = $this->reconcilePaidItems(
                    $locked,
                    $totals['items'],
                    $actorId,
                );
            } elseif ($itemsChanged) {
                // Soft deletion preserves reversed FIFO layers for the audit trail.
                $locked->items()->delete();
                $currentItems = $locked->items()->createMany($totals['items']);
            } else {
                // Preserve the original item IDs and their paid FIFO cost layers.
                // A price, tax, notes or production-status edit must not take a
                // fresh cost snapshot from today's product/batch prices.
                $remaining = $locked->items->groupBy(fn ($item): string => $this->itemIdentity($item->product_id, $item->quantity));
                $currentItems = collect();
                foreach ($totals['items'] as $attributes) {
                    $key = $this->itemIdentity($attributes['product_id'], $attributes['quantity']);
                    $item = $remaining[$key]->shift();
                    // Same identity: preserve the original classification/unit/cost snapshot,
                    // including service-only invoices without FIFO layers.
                    unset($attributes['purchase_cost_snapshot'], $attributes['unit_hpp'], $attributes['hpp_total'], $attributes['metadata'], $attributes['unit'], $attributes['packaging_unit'], $attributes['order_increment'], $attributes['moq_quantity']);
                    $item->update($attributes);
                    $currentItems->push($item);
                }
            }

            $stockAlerts = match (true) {
                $verifiedPaid > 0 && $itemsChanged && $hasConsumedInventory => $this->stockAlertsFor($currentItems),
                $verifiedPaid > 0 && ! $hasConsumedInventory => $this->recordInvoiceSaleMovements->handle($locked, $currentItems, $actorId),
                default => $locked->metadata['inventory_alerts'] ?? [],
            };

            $totalHpp = (float) $locked->items()->sum('hpp_total');
            $companyShipping = $locked->shipping_type === Invoice::SHIPPING_COMPANY_FREE_SHIPPING
                ? (float) $locked->shipping_cost
                : 0;

            // paid = 0 -> unpaid, 0 < paid < total -> partial, paid >= total
            // -> paid. The guard above already ruled out paid > total.
            $paymentStatus = match (true) {
                $verifiedPaid <= 0 => Invoice::PAYMENT_UNPAID,
                $verifiedPaid >= $totals['total_amount'] => Invoice::PAYMENT_PAID,
                default => Invoice::PAYMENT_PARTIAL,
            };

            $locked->forceFill([
                'metadata' => array_merge($locked->metadata ?? [], [
                    'inventory_alerts' => $stockAlerts,
                ]),
                'total_hpp' => round($totalHpp, 2),
                'gross_profit' => round((float) $locked->total_amount - $totalHpp - $companyShipping, 2),
                'payment_status' => $paymentStatus,
                'paid_at' => $paymentStatus === Invoice::PAYMENT_PAID ? ($locked->paid_at ?? now()) : null,
            ])->save();

            // A still-draft invoice being edited is the routine, low-stakes
            // case (nothing sent/paid/produced against it yet); an invoice
            // already issued is the case this whole change exists for, so it
            // gets its own event name and a higher risk level for review.
            $isPostIssuance = $statusBefore !== Invoice::STATUS_DRAFT;

            $this->activityLogger->record(
                module: 'invoice',
                action: $isPostIssuance ? 'updated_after_issuance' : 'draft_updated',
                event: $isPostIssuance ? 'Invoice updated after issuance' : 'Invoice draft updated',
                description: $isPostIssuance
                    ? "Invoice {$locked->invoice_number} diedit setelah diterbitkan."
                    : "Draft invoice {$locked->invoice_number} diperbarui.",
                subject: $locked,
                metadata: [
                    'invoice_number' => $locked->invoice_number,
                    'status' => $statusBefore,
                    'production_status' => $productionStatusBefore,
                    'total_before' => $totalBefore,
                    'total_after' => (float) $locked->total_amount,
                    'hpp_before' => $hppBefore,
                    'hpp_after' => (float) $locked->total_hpp,
                    'inventory_items_changed' => $itemsChanged,
                    'items_before' => $itemsBefore,
                    'items_after' => $currentItems->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'sku' => $item->sku,
                        'quantity' => (float) $item->quantity,
                        'hpp_total' => (float) $item->hpp_total,
                    ])->values()->all(),
                ],
                riskLevel: $isPostIssuance ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_LOW,
            );

            return $locked->refresh()->load('items');
        });
    }

    /** @param iterable<array<string, mixed>> $items */
    private function inventoryIdentity(iterable $items): array
    {
        $identity = [];
        foreach ($items as $item) {
            $identity[] = $this->itemIdentity($item['product_id'], $item['quantity']);
        }

        sort($identity, SORT_STRING);

        return $identity;
    }

    private function itemIdentity(int|string $productId, float|string $quantity): string
    {
        return (int) $productId.':'.number_format((float) $quantity, 4, '.', '');
    }

    /**
     * @param  list<array<string, mixed>>  $newItems
     * @return Collection<int, InvoiceItem>
     */
    private function reconcilePaidItems(Invoice $invoice, array $newItems, ?int $actorId): Collection
    {
        $oldItems = $invoice->items()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        Product::query()
            ->whereIn('id', collect($newItems)->pluck('product_id')->merge($oldItems->pluck('product_id'))->filter()->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $unused = $oldItems->keyBy(fn (InvoiceItem $item): int => $item->getKey());
        $current = collect();

        foreach ($newItems as $attributes) {
            /** @var InvoiceItem|null $match */
            $match = $unused->first(fn (InvoiceItem $item): bool => (int) $item->product_id === (int) $attributes['product_id']);

            if (! $match instanceof InvoiceItem) {
                /** @var InvoiceItem $created */
                $created = $invoice->items()->create($attributes);
                $this->fifoInventory->consume($invoice, $created, $actorId);
                $current->push($created->refresh());

                continue;
            }

            $unused->forget($match->getKey());
            $oldQuantity = round((float) $match->quantity, 4);
            $newQuantity = round((float) $attributes['quantity'], 4);
            $delta = round($newQuantity - $oldQuantity, 4);

            if ($match->tracksInventory()) {
                unset(
                    $attributes['purchase_cost_snapshot'],
                    $attributes['unit_hpp'],
                    $attributes['hpp_total'],
                );
            }

            $match->update($attributes);

            if ($match->tracksInventory() && $delta > 0) {
                $this->fifoInventory->consumeAdditional($invoice, $match, $delta, $actorId);
                $this->fifoInventory->refreshItemCost($match->refresh());
            } elseif ($match->tracksInventory() && $delta < 0) {
                $this->fifoInventory->restoreItemQuantity(
                    $invoice,
                    $match,
                    abs($delta),
                    $actorId,
                    "Pengembalian FIFO karena quantity invoice {$invoice->invoice_number} turun dari {$oldQuantity} ke {$newQuantity}",
                );
                $this->fifoInventory->refreshItemCost($match->refresh());
            }

            $current->push($match->refresh());
        }

        foreach ($unused as $removed) {
            if ($removed->tracksInventory() && $removed->costLayers()->whereNull('reversed_at')->exists()) {
                $this->fifoInventory->restoreItemQuantity(
                    $invoice,
                    $removed,
                    (float) $removed->quantity,
                    $actorId,
                    "Pengembalian FIFO karena item dihapus dari invoice {$invoice->invoice_number}",
                );
            }

            $removed->delete();
        }

        return $current;
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return list<array<string, mixed>>
     */
    private function stockAlertsFor(Collection $items): array
    {
        return $items
            ->filter(fn (InvoiceItem $item): bool => $item->tracksInventory())
            ->map(function (InvoiceItem $item): ?array {
                $product = $item->product?->refresh();
                if (! $product instanceof Product) {
                    return null;
                }

                $stock = (float) ($product->stock ?? 0);
                $minimumStock = $product->minimumStockValue();
                if ($stock >= 0 && $stock > $minimumStock) {
                    return null;
                }

                return [
                    'product_id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'stock' => $stock,
                    'minimum_stock' => $minimumStock,
                    'is_negative' => $stock < 0,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
