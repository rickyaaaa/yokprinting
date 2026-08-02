<?php

namespace Tests\Unit;

use App\Services\Invoices\CalculateInvoicePreview;
use App\Services\Invoices\CalculateInvoiceTotals;
use PHPUnit\Framework\TestCase;

class CalculateInvoicePreviewTest extends TestCase
{
    public function test_client_provided_derived_amounts_are_ignored(): void
    {
        $preview = (new CalculateInvoicePreview(new CalculateInvoiceTotals))->calculate([
            'items' => [
                [
                    'name' => 'Cup Injection 12Oz',
                    'quantity' => 500,
                    'unit' => 'Pcs',
                    'unit_price' => 300,
                    'quantity_label' => '1 Pcs',
                    'line_total' => 1,
                ],
            ],
            'subtotal' => 1,
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'discount_amount' => 1,
            'tax_enabled' => true,
            'tax_rate' => 11,
            'tax_amount' => 1,
            'shipping_cost' => 10000,
            'is_free_shipping' => false,
            'total_amount' => 1,
            'dp_required_percent' => 50,
            'dp_amount' => 1,
            'remaining_amount' => 1,
            'remaining_payment' => 1,
        ]);

        $this->assertSame('500 Pcs', $preview['items'][0]['quantity_label']);
        $this->assertSame(150000.0, $preview['items'][0]['line_total']);
        $this->assertSame(150000.0, $preview['subtotal']);
        $this->assertSame(7500.0, $preview['discount_amount']);
        $this->assertSame(15675.0, $preview['tax_amount']);
        $this->assertSame(168175.0, $preview['total_amount']);
        $this->assertSame(168175.0, $preview['grand_total']);
        $this->assertSame(84087.5, $preview['dp_amount']);
        $this->assertSame(84087.5, $preview['remaining_amount']);
        $this->assertSame(84087.5, $preview['remaining_payment']);
    }
}
