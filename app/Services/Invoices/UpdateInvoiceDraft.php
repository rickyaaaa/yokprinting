<?php

namespace App\Services\Invoices;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Security\ActivityLogger;
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
     * its lifecycle (draft, sent, and any production_status) as long as it
     * isn't cancelled; see Invoice::isEditable(). Editing never touches
     * `status`/`sent_at` (an already-sent invoice stays sent) or
     * `production_status`/`order_process_status`/mockup/template/theme
     * unless the caller explicitly sent a new value for them.
     *
     * Sale stock movements are deducted at invoice creation time (not at
     * "send" time, unlike GoodsReceipt), so editing must reverse the old
     * items' FIFO/stock impact before recording the new ones - reuses the
     * exact same FifoInventoryService::restoreInvoice()/consume() pair the
     * cancel flow already relies on, so a partially-closed FIFO deficit (or
     * any other state restoreInvoice() already refuses to unwind) blocks
     * the edit with the same clear error instead of corrupting inventory.
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
                'quantity' => (float) $item->quantity,
            ])->values()->all();

            // Restore the old items' FIFO/stock impact only now that every
            // early-exit validation has passed - restoreInvoice() itself can
            // still refuse (e.g. a receipt already closed part of a FIFO
            // deficit this invoice created), which correctly aborts the
            // whole edit via the transaction rollback.
            $this->fifoInventory->restoreInvoice($locked, $actorId);

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
                    'items_before' => $itemsBefore,
                    'items_after' => $createdItems->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => (float) $item->quantity,
                    ])->values()->all(),
                ],
                riskLevel: $isPostIssuance ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_LOW,
            );

            return $locked->refresh()->load('items');
        });
    }
}
