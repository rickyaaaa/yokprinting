<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreInvoiceDraftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_draft_is_stored_with_server_calculated_totals(): void
    {
        $response = $this->postJson(route('api.invoices.drafts.store'), $this->validPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Draft invoice berhasil disimpan.')
            ->assertJsonPath('data.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.production_status', 'draft')
            ->assertJsonPath('data.production_status_label', 'Drafting')
            ->assertJsonPath('data.subtotal', '21250000.00')
            ->assertJsonPath('data.discount_amount', '1062500.00')
            ->assertJsonPath('data.tax_amount', '2220625.00')
            ->assertJsonPath('data.total_amount', '22408125.00')
            ->assertJsonPath('data.items_count', 2);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-0001',
            'customer_id' => 1,
            'status' => 'draft',
            'total_amount' => 22408125,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'product_id' => 1,
            'product_name' => 'Paket Desain Identitas Brand',
            'sku' => 'JSA-BRAND-01',
            'subtotal' => 12500000,
        ]);
        $this->assertDatabaseCount('invoice_items', 2);
    }

    public function test_invoice_draft_stores_yokprinting_cup_specs_and_production_fields(): void
    {
        $payload = $this->validPayload();
        $payload['items'] = [
            [
                'product_id' => 1,
                'product_name' => 'Sablon Cup 16 Oz Oval',
                'sku' => 'CUP-16OV-8G-2S',
                'cup_size' => '16 Oz',
                'cup_model' => 'Oval',
                'grammage' => '8gr',
                'screen_printing_color' => 'Hitam',
                'sides' => 2,
                'moq_quantity' => 1000,
                'order_increment' => 1000,
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
            'product_name' => 'Sablon Cup 16 Oz Oval',
            'sku' => 'CUP-16OV-8G-2S',
            'cup_size' => '16 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'sides' => 2,
            'moq_quantity' => 1000,
            'order_increment' => 1000,
            'packaging_unit' => 'pcs',
            'description' => 'Sablon Cup 16 Oz Oval (8gr) - 1 Warna (Tinta Hitam - 2 Sisi)',
            'subtotal' => 1700000,
        ]);
    }

    public function test_invoice_draft_rejects_quantities_below_moq_or_wrong_increment(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['moq_quantity'] = 1000;
        $payload['items'][0]['order_increment'] = 1000;
        $payload['items'][0]['quantity'] = 1500;

        $this->postJson(route('api.invoices.drafts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
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
        return [
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0090',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Paket Desain Identitas Brand',
                    'sku' => 'JSA-BRAND-01',
                    'quantity' => 1,
                    'price' => 12500000,
                ],
                [
                    'product_id' => 2,
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
