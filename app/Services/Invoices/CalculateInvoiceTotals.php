<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class CalculateInvoiceTotals
{
    /**
     * Calculate normalized line items and authoritative invoice totals.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array{type: string, value: int|float|string}  $discount
     * @param  array{enabled: bool, rate: int|float|string}  $tax
     * @return array{
     *     items: list<array<string, mixed>>,
     *     subtotal: float,
     *     discount_type: string,
     *     discount_value: float,
     *     discount_amount: float,
     *     product_revenue: float,
     *     taxable_amount: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     shipping_type: string,
     *     shipping_cost: float,
     *     total_hpp: float,
     *     gross_profit: float,
     *     total_amount: float
     * }
     */
    public function calculate(
        array $items,
        array $discount,
        array $tax,
        string $shippingType = Invoice::SHIPPING_NONE,
        int|float|string $shippingCost = 0,
    ): array {
        $lineItems = collect($items)
            ->values()
            ->map(function (array $item, int $index): array {
                $quantity = round(max(0, (float) $item['quantity']), 4);
                $unitPrice = $this->money(max(0, (float) $item['price']));
                $purchaseCostSnapshot = $this->money(max(0, (float) ($item['purchase_cost_snapshot'] ?? 0)));
                $subtotal = $this->money($quantity * $unitPrice);

                return [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'] ?? "Produk #{$item['product_id']}",
                    'sku' => $item['sku'] ?? null,
                    'cup_size' => $item['cup_size'] ?? null,
                    'cup_model' => $item['cup_model'] ?? null,
                    'grammage' => $item['grammage'] ?? null,
                    'screen_printing_color' => $item['screen_printing_color'] ?? null,
                    'jenis_cetak' => $item['jenis_cetak'] ?? null,
                    'moq_quantity' => $item['moq_quantity'] ?? null,
                    'order_increment' => $item['order_increment'] ?? null,
                    'packaging_unit' => $item['packaging_unit'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit' => 'Pcs',
                    'unit_price' => $unitPrice,
                    'purchase_cost_snapshot' => $purchaseCostSnapshot,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'sort_order' => $index,
                    'metadata' => [
                        'industry' => 'sablon-cup-fnb',
                    ],
                ];
            });

        $subtotal = $this->money($lineItems->sum('subtotal'));
        $discountType = $discount['type'];
        $discountValue = $this->money(max(0, (float) $discount['value']));
        $discountAmount = $discountType === 'percentage'
            ? $this->money($subtotal * min(100, $discountValue) / 100)
            : $this->money(min($subtotal, $discountValue));
        $discountAmount = min($subtotal, $discountAmount);
        $taxableAmount = $this->money(max(0, $subtotal - $discountAmount));
        $taxRate = $tax['enabled']
            ? round(min(100, max(0, (float) $tax['rate'])), 2)
            : 0.0;
        $taxAmount = $this->money($taxableAmount * $taxRate / 100);
        $productRevenue = $taxableAmount;
        $normalizedShippingType = in_array($shippingType, [
            Invoice::SHIPPING_NONE,
            Invoice::SHIPPING_PAID_BY_CUSTOMER,
            Invoice::SHIPPING_COMPANY_FREE_SHIPPING,
        ], true) ? $shippingType : Invoice::SHIPPING_NONE;
        $normalizedShippingCost = $normalizedShippingType === Invoice::SHIPPING_NONE
            ? 0.0
            : $this->money(max(0, (float) $shippingCost));
        $totalHpp = $this->money($lineItems->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['purchase_cost_snapshot'],
        ));
        $invoiceTotalShipping = $normalizedShippingType === Invoice::SHIPPING_PAID_BY_CUSTOMER
            ? $normalizedShippingCost
            : 0.0;
        $totalAmount = $this->money($taxableAmount + $taxAmount + $invoiceTotalShipping);
        $companyPaidShipping = $normalizedShippingType === Invoice::SHIPPING_COMPANY_FREE_SHIPPING
            ? $normalizedShippingCost
            : 0.0;

        return [
            'items' => $lineItems->all(),
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'product_revenue' => $productRevenue,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'shipping_type' => $normalizedShippingType,
            'shipping_cost' => $normalizedShippingCost,
            'total_hpp' => $totalHpp,
            'gross_profit' => $this->money($totalAmount - $totalHpp - $companyPaidShipping),
            'total_amount' => $totalAmount,
        ];
    }

    private function money(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
