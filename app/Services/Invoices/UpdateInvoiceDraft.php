<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Services\Inventory\FifoInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceDraft
{
    public function __construct(
        private readonly CalculateInvoiceTotals $calculateInvoiceTotals,
        private readonly SnapshotInvoiceItems $snapshotInvoiceItems,
        private readonly RecordInvoiceSaleMovements $recordInvoiceSaleMovements,
        private readonly FifoInventoryService $fifoInventory,
    ) {}

    /**
     * Replace a draft invoice's header and line items. Only allowed while
     * the invoice is still draft - once sent, items are locked for audit
     * history, matching PurchaseOrder/GoodsReceipt.
     *
     * Sale stock movements are deducted at invoice creation time (not at
     * "send" time, unlike GoodsReceipt), so editing must reverse the old
     * items' stock impact before recording the new ones. That reversal is
     * always safe here: a still-draft invoice can't have payments recorded
     * against it or be delivered yet, so nothing else could have happened
     * to depend on its current items in between - no "was this reversed
     * safely" check like CancelGoodsReceipt needs is required.
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
                    'status' => 'Invoice yang sudah dikirim/dibatalkan tidak bisa diedit. Batalkan lalu buat invoice baru kalau item/harga perlu berubah.',
                ]);
            }

            $this->fifoInventory->restoreInvoice($locked, $actorId);

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
                // order-process tracking) should ever change them.
                'order_process_status' => $locked->order_process_status,
                'mockup_url' => $locked->mockup_url,
                'template' => $locked->template,
                'theme_color' => $locked->theme_color,
                'total_hpp' => $totals['total_hpp'],
                'gross_profit' => $totals['gross_profit'],
                'production_status' => $data['production_status'] ?? $locked->production_status,
                'dp_required_percent' => $data['dp_required_percent'] ?? $locked->dp_required_percent,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'design_notes' => $data['design_notes'] ?? null,
            ]);

            $locked->items()->delete();
            $createdItems = $locked->items()->createMany($totals['items']);

            $stockAlerts = $this->recordInvoiceSaleMovements->handle($locked, $createdItems, $actorId);

            $totalHpp = (float) $locked->items()->sum('hpp_total');
            $companyShipping = $locked->shipping_type === Invoice::SHIPPING_COMPANY_FREE_SHIPPING
                ? (float) $locked->shipping_cost
                : 0;

            $locked->forceFill([
                'metadata' => array_merge($locked->metadata ?? [], [
                    'inventory_alerts' => $stockAlerts,
                ]),
                'total_hpp' => round($totalHpp, 2),
                'gross_profit' => round((float) $locked->total_amount - $totalHpp - $companyShipping, 2),
            ])->save();

            return $locked->refresh()->load('items');
        });
    }
}
