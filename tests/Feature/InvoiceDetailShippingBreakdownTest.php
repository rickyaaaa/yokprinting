<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * PHASE 5: "Rincian Tagihan" on the invoice detail page must show the
 * shipping breakdown, built purely from the existing shipping_type/
 * shipping_cost/is_free_shipping fields - no new field, no change to how
 * total_amount/gross_profit are calculated (CalculateInvoiceTotals is
 * untouched).
 */
class InvoiceDetailShippingBreakdownTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_without_shipping_shows_no_ongkir_line(): void
    {
        $invoice = $this->invoice([
            'shipping_type' => Invoice::SHIPPING_NONE,
            'shipping_cost' => 0,
            'is_free_shipping' => false,
            'subtotal' => 250000,
            'tax_amount' => 0,
            'total_amount' => 250000,
        ]);

        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertDontSee('Ongkir');
    }

    public function test_invoice_with_customer_paid_shipping_shows_the_amount(): void
    {
        // Exact client example: subtotal 250k + ongkir 50k = total 300k.
        $invoice = $this->invoice([
            'shipping_type' => Invoice::SHIPPING_PAID_BY_CUSTOMER,
            'shipping_cost' => 50000,
            'is_free_shipping' => false,
            'subtotal' => 250000,
            'tax_amount' => 0,
            'total_amount' => 300000,
        ]);

        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertSeeInOrder(['Subtotal', 'Rp250.000', 'Ongkir', 'Rp50.000', 'Total invoice', 'Rp300.000']);
    }

    public function test_invoice_with_company_paid_free_shipping_shows_gratis_and_does_not_bill_customer(): void
    {
        $invoice = $this->invoice([
            'shipping_type' => Invoice::SHIPPING_COMPANY_FREE_SHIPPING,
            'shipping_cost' => 35000,
            'is_free_shipping' => true,
            'subtotal' => 250000,
            'tax_amount' => 0,
            // Existing rule (CalculateInvoiceTotals, untouched): company-paid
            // shipping is never added into total_amount.
            'total_amount' => 250000,
        ]);

        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertSee('Ongkir')
            ->assertSee('Gratis (ditanggung perusahaan)')
            ->assertSeeInOrder(['Total invoice', 'Rp250.000'])
            ->assertDontSee('Rp35.000');
    }

    public function test_editing_shipping_on_an_invoice_updates_the_detail_breakdown(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Edit Shipping']);
        $product = Product::query()->create([
            'name' => 'Produk Uji Pengiriman',
            'sku' => 'EDIT-SHIP-01',
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        $response = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 250000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
            'shipping_type' => Invoice::SHIPPING_NONE,
        ])->assertCreated();

        $invoice = Invoice::query()->findOrFail($response->json('data.id'));

        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertDontSee('Ongkir');

        $this->patchJson(route('api.invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 250000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
            'shipping_type' => Invoice::SHIPPING_PAID_BY_CUSTOMER,
            'shipping_cost' => 50000,
        ])->assertOk();

        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertSeeInOrder(['Ongkir', 'Rp50.000', 'Total invoice', 'Rp300.000']);
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(array $overrides): Invoice
    {
        $customer = Customer::query()->create(['name' => 'PT Shipping Detail Test']);

        return Invoice::query()->create(array_merge([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SHIP-'.random_int(1000, 9999),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
        ], $overrides));
    }
}
