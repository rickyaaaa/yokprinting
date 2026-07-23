<?php

namespace App\Services\Invoices;

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
     *     taxable_amount: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     total_amount: float
     * }
     */
    public function calculate(array $items, array $discount, array $tax): array
    {
        $lineItems = collect($items)
            ->values()
            ->map(function (array $item, int $index): array {
                $quantity = round(max(0, (float) $item['quantity']), 4);
                $unitPrice = $this->money(max(0, (float) $item['price']));
                $subtotal = $this->money($quantity * $unitPrice);

                return [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'] ?? "Produk #{$item['product_id']}",
                    'sku' => $item['sku'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'sort_order' => $index,
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

        return [
            'items' => $lineItems->all(),
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $this->money($taxableAmount + $taxAmount),
        ];
    }

    private function money(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
