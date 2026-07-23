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
