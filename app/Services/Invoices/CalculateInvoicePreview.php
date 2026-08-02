<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class CalculateInvoicePreview
{
    public function __construct(
        private readonly CalculateInvoiceTotals $calculateInvoiceTotals,
    ) {}

    /**
     * Replace all client-provided derived amounts with server calculations.
     *
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function calculate(array $preview): array
    {
        $items = collect($preview['items'])
            ->values()
            ->map(fn (array $item, int $index): array => [
                'product_id' => $index + 1,
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['unit_price'],
            ])
            ->all();
        $isFreeShipping = (bool) ($preview['is_free_shipping'] ?? false);
        $shippingCost = (float) ($preview['shipping_cost'] ?? 0);
        $shippingType = $isFreeShipping
            ? Invoice::SHIPPING_COMPANY_FREE_SHIPPING
            : ($shippingCost > 0
                ? Invoice::SHIPPING_PAID_BY_CUSTOMER
                : Invoice::SHIPPING_NONE);
        $totals = $this->calculateInvoiceTotals->calculate(
            $items,
            [
                'type' => $preview['discount_type'] ?? 'percentage',
                'value' => $preview['discount_value'] ?? 0,
            ],
            [
                'enabled' => (bool) ($preview['tax_enabled'] ?? false),
                'rate' => $preview['tax_rate'] ?? 0,
            ],
            $shippingType,
            $shippingCost,
        );
        $preview['items'] = collect($preview['items'])
            ->values()
            ->map(function (array $item, int $index) use ($totals): array {
                $calculated = $totals['items'][$index];
                $unit = $item['unit'] ?? 'Pcs';

                return [
                    ...$item,
                    'quantity' => $calculated['quantity'],
                    'quantity_label' => $this->quantityLabel((float) $calculated['quantity'], $unit),
                    'unit_price' => $calculated['unit_price'],
                    'line_total' => $calculated['subtotal'],
                ];
            })
            ->all();
        $dpRequiredPercent = round((float) ($preview['dp_required_percent'] ?? 50), 2);
        $dpAmount = round($totals['total_amount'] * $dpRequiredPercent / 100, 2, PHP_ROUND_HALF_UP);
        $remainingAmount = round(max(0, $totals['total_amount'] - $dpAmount), 2, PHP_ROUND_HALF_UP);

        return [
            ...$preview,
            'subtotal' => $totals['subtotal'],
            'discount_type' => $totals['discount_type'],
            'discount_value' => $totals['discount_value'],
            'discount_amount' => $totals['discount_amount'],
            'tax_enabled' => $totals['tax_rate'] > 0,
            'tax_rate' => $totals['tax_rate'],
            'tax_amount' => $totals['tax_amount'],
            'shipping_cost' => $totals['shipping_cost'],
            'is_free_shipping' => $isFreeShipping,
            'total_amount' => $totals['total_amount'],
            'grand_total' => $totals['total_amount'],
            'dp_required_percent' => $dpRequiredPercent,
            'dp_amount' => $dpAmount,
            'remaining_amount' => $remainingAmount,
            'remaining_payment' => $remainingAmount,
        ];
    }

    private function quantityLabel(float $quantity, string $unit): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',');

        return "{$formatted} {$unit}";
    }
}
