<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateInvoiceDraft
{
    public function __construct(
        private readonly CalculateInvoiceTotals $calculateInvoiceTotals,
        private readonly GenerateInvoiceNumber $generateInvoiceNumber,
        private readonly SnapshotInvoiceItems $snapshotInvoiceItems,
        private readonly RecordInvoiceSaleMovements $recordInvoiceSaleMovements,
    ) {}

    /**
     * Persist a new invoice draft and all line items atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?int $creatorId = null): Invoice
    {
        return DB::transaction(function () use ($data, $creatorId): Invoice {
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
                'is_free_shipping' => $isFreeShipping,
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
            $stockAlerts = $this->recordInvoiceSaleMovements->handle($invoice, $createdItems, $creatorId);

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
}
