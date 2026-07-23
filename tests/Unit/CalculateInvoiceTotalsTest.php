<?php

namespace Tests\Unit;

use App\Services\Invoices\CalculateInvoiceTotals;
use PHPUnit\Framework\TestCase;

class CalculateInvoiceTotalsTest extends TestCase
{
    public function test_percentage_discount_and_tax_are_calculated_in_the_correct_order(): void
    {
        $totals = (new CalculateInvoiceTotals)->calculate(
            [
                ['product_id' => 1, 'quantity' => 2, 'price' => 100000],
                ['product_id' => 2, 'quantity' => 1, 'price' => 50000],
            ],
            ['type' => 'percentage', 'value' => 10],
            ['enabled' => true, 'rate' => 11],
        );

        $this->assertSame(250000.0, $totals['subtotal']);
        $this->assertSame(25000.0, $totals['discount_amount']);
        $this->assertSame(225000.0, $totals['taxable_amount']);
        $this->assertSame(24750.0, $totals['tax_amount']);
        $this->assertSame(249750.0, $totals['total_amount']);
    }

    public function test_fixed_discount_is_capped_at_subtotal_and_disabled_tax_is_zero(): void
    {
        $totals = (new CalculateInvoiceTotals)->calculate(
            [
                ['product_id' => 1, 'quantity' => 1, 'price' => 75000],
            ],
            ['type' => 'fixed', 'value' => 100000],
            ['enabled' => false, 'rate' => 11],
        );

        $this->assertSame(75000.0, $totals['discount_amount']);
        $this->assertSame(0.0, $totals['taxable_amount']);
        $this->assertSame(0.0, $totals['tax_rate']);
        $this->assertSame(0.0, $totals['tax_amount']);
        $this->assertSame(0.0, $totals['total_amount']);
    }

    public function test_line_and_invoice_amounts_use_half_up_currency_rounding(): void
    {
        $totals = (new CalculateInvoiceTotals)->calculate(
            [
                ['product_id' => 1, 'quantity' => 1.005, 'price' => 10],
            ],
            ['type' => 'percentage', 'value' => 0],
            ['enabled' => true, 'rate' => 10],
        );

        $this->assertSame(10.05, $totals['items'][0]['subtotal']);
        $this->assertSame(1.01, $totals['tax_amount']);
        $this->assertSame(11.06, $totals['total_amount']);
    }
}
