<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class StoreInvoiceDraftApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_draft_is_stored_with_server_calculated_totals(): void
    {
        $payload = $this->validPayload();
        $response = $this->postJson(route('api.invoices.drafts.store'), $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Invoice berhasil disimpan.')
            ->assertJsonPath('data.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sent_at', null)
            ->assertJsonPath('data.production_status', 'draft')
            ->assertJsonPath('data.production_status_label', 'Drafting')
            ->assertJsonPath('data.subtotal', '21250000.00')
            ->assertJsonPath('data.discount_amount', '1062500.00')
            ->assertJsonPath('data.tax_amount', '2220625.00')
            ->assertJsonPath('data.shipping_type', 'none')
            ->assertJsonPath('data.total_hpp', '10250000.00')
            ->assertJsonPath('data.gross_profit', '12158125.00')
            ->assertJsonPath('data.total_amount', '22408125.00')
            ->assertJsonPath('data.items_count', 2);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-0001',
            'customer_id' => $payload['customer_id'],
            'status' => 'draft',
            'total_amount' => 22408125,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'product_id' => $payload['items'][0]['product_id'],
            'product_name' => 'Paket Desain Identitas Brand',
            'sku' => 'JSA-BRAND-01',
            'purchase_cost_snapshot' => 6000000,
            'subtotal' => 12500000,
        ]);
        $this->assertDatabaseCount('invoice_items', 2);
    }

    public function test_invoice_draft_stores_yokprinting_cup_specs_and_production_fields(): void
    {
        $payload = $this->validPayload();
        Product::query()
            ->whereKey($payload['items'][0]['product_id'])
            ->update([
                'minimum_order_qty' => 500,
                'package_conversion' => 500,
                'unit' => 'Pcs',
            ]);
        $payload['items'] = [
            [
                'product_id' => 1,
                'product_name' => 'Sablon Cup 12 Oz Oval',
                'sku' => 'H-016',
                'cup_size' => '12 Oz',
                'cup_model' => 'Oval',
                'grammage' => '8gr',
                'screen_printing_color' => 'Hitam',
                'jenis_cetak' => '2 warna',
                'moq_quantity' => 500,
                'order_increment' => 500,
                'packaging_unit' => 'pcs',
                'quantity' => 2000,
                'price' => 850,
            ],
        ];
        $payload['discount']['value'] = 0;
        $payload['tax']['enabled'] = false;
        $payload['production_status'] = 'awaiting_dp';
        $payload['dp_required_percent'] = 50;
        $payload['design_notes'] = 'Logo tengah, tinta hitam, tunggu ACC mockup.';
        $payload['mockup_url'] = 'https://yokprinting.id/mockup/INV-2026-0090';

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.production_status', 'awaiting_dp')
            ->assertJsonPath('data.production_status_label', 'Menunggu DP')
            ->assertJsonPath('data.subtotal', '1700000.00')
            ->assertJsonPath('data.total_amount', '1700000.00')
            ->assertJsonPath('data.items_count', 1);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-0001',
            'production_status' => 'awaiting_dp',
            'dp_required_percent' => 50,
            'design_notes' => 'Logo tengah, tinta hitam, tunggu ACC mockup.',
            'mockup_url' => 'https://yokprinting.id/mockup/INV-2026-0090',
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'product_name' => 'Sablon Cup 12 Oz Oval',
            'sku' => 'H-016',
            'cup_size' => '12 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'jenis_cetak' => '2 warna',
            'moq_quantity' => 500,
            'order_increment' => 500,
            'packaging_unit' => 'Pcs',
            'description' => 'Sablon Cup 12 Oz Oval (8gr) - 2 warna (Tinta Hitam)',
            'subtotal' => 1700000,
        ]);
    }

    public function test_invoice_draft_rejects_non_12_oz_cup_size(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['cup_size'] = '16 Oz';

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.cup_size']);
    }

    public function test_invoice_draft_rejects_quantities_below_moq_or_wrong_increment(): void
    {
        $payload = $this->validPayload();
        Product::query()
            ->whereKey($payload['items'][0]['product_id'])
            ->update([
                'minimum_order_qty' => 500,
                'package_conversion' => 500,
            ]);
        $payload['items'][0]['quantity'] = 1750;

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_invoice_draft_accepts_five_hundred_quantity_increment(): void
    {
        $payload = $this->validPayload();
        Product::query()
            ->whereKey($payload['items'][0]['product_id'])
            ->update([
                'minimum_order_qty' => 1000,
                'package_conversion' => 500,
            ]);
        $payload['items'] = [
            [
                ...$payload['items'][0],
                'quantity' => 500,
                'price' => 1000,
            ],
        ];
        $payload['discount']['value'] = 0;
        $payload['tax']['enabled'] = false;

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '500000.00');
    }

    public function test_customer_paid_shipping_is_added_to_invoice_total_without_inflating_profit(): void
    {
        $payload = $this->validPayload();
        $payload['discount']['value'] = 0;
        $payload['tax']['enabled'] = false;
        $payload['shipping_type'] = 'paid_by_customer';
        $payload['shipping_cost'] = 50000;

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.shipping_type', 'paid_by_customer')
            ->assertJsonPath('data.shipping_cost', '50000.00')
            ->assertJsonPath('data.total_hpp', '10250000.00')
            ->assertJsonPath('data.gross_profit', '11050000.00')
            ->assertJsonPath('data.total_amount', '21300000.00');

        $this->assertDatabaseHas('invoices', [
            'shipping_type' => 'paid_by_customer',
            'shipping_cost' => 50000,
            'total_hpp' => 10250000,
            'gross_profit' => 11050000,
            'total_amount' => 21300000,
        ]);
    }

    public function test_company_free_shipping_reduces_gross_profit_and_keeps_invoice_total_clean(): void
    {
        $payload = $this->validPayload();
        $payload['discount']['value'] = 0;
        $payload['tax']['enabled'] = false;
        $payload['shipping_cost'] = 50000;
        $payload['is_free_shipping'] = true;
        $payload['order_process_status'] = 'in_production';

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.shipping_type', 'company_free_shipping')
            ->assertJsonPath('data.is_free_shipping', true)
            ->assertJsonPath('data.order_process_status', 'in_production')
            ->assertJsonPath('data.total_hpp', '10250000.00')
            ->assertJsonPath('data.gross_profit', '10950000.00')
            ->assertJsonPath('data.total_amount', '21250000.00');
    }

    public function test_invoice_draft_validation_returns_field_errors(): void
    {
        $payload = $this->validPayload();
        $payload['due_date'] = '2026-07-20';
        $payload['items'] = [];
        $payload['discount']['value'] = 150;

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'due_date',
                'items',
                'discount.value',
            ]);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $customer = Customer::query()->create([
            'name' => 'PT Pelanggan Invoice',
            'email' => fake()->unique()->safeEmail(),
            'address' => 'Jl. Invoice No. 1',
            'city' => 'Tangerang',
        ]);
        $brandPackage = Product::query()->create([
            'name' => 'Paket Desain Identitas Brand',
            'sku' => 'JSA-BRAND-01',
            'category' => 'Jasa kreatif',
            'purchase_price' => 6000000,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $websitePackage = Product::query()->create([
            'name' => 'Website Company Profile',
            'sku' => 'JSA-WEB-03',
            'category' => 'Jasa kreatif',
            'purchase_price' => 4250000,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        return [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0090',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'items' => [
                [
                    'product_id' => $brandPackage->id,
                    'product_name' => 'Paket Desain Identitas Brand',
                    'sku' => 'JSA-BRAND-01',
                    'quantity' => 1,
                    'price' => 12500000,
                ],
                [
                    'product_id' => $websitePackage->id,
                    'product_name' => 'Website Company Profile',
                    'sku' => 'JSA-WEB-03',
                    'quantity' => 1,
                    'price' => 8750000,
                ],
            ],
            'discount' => [
                'type' => 'percentage',
                'value' => 5,
            ],
            'tax' => [
                'enabled' => true,
                'rate' => 11,
            ],
            'notes' => 'Terima kasih.',
            'terms' => 'Pembayaran maksimal 14 hari.',
        ];
    }
}
