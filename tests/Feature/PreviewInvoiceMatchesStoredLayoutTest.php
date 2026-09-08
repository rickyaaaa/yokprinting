<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\CalculateInvoicePreview;
use App\Services\Invoices\GenerateInvoicePdf;
use ReflectionMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-confirmed: the customer-facing invoice names an item by its product
 * name and nothing else. SKU and the generated "Sablon ..." spec description
 * are working detail that stays on the in-app Rincian tagihan.
 *
 * Before this the preview showed the description in place of the product, so
 * a line read "Sablon Cup 12 Oz Datar (8gr)..." and the actual product -
 * "Tutup Strawless SJP D93" - appeared nowhere on the document.
 */
class PreviewInvoiceMatchesStoredLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_preview_pdf_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $response = $this->postJson('/api/invoices/preview/pdf', $this->payload());

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith("%PDF", $pdf);
    }

    public function test_the_downloaded_preview_prints_the_product_name_not_the_description(): void
    {
        // The exact complaint: the download kept showing the "Sablon ..." text
        // instead of the product. Rendered as HTML rather than PDF bytes so the
        // assertion can actually read what was printed.
        $preview = app(CalculateInvoicePreview::class)->calculate($this->payload());

        $html = view('pdf.invoices.preview', ['preview' => $preview])->render();

        $this->assertStringContainsString('Tutup Strawless SJP D93', $html);
        $this->assertStringNotContainsString('Sablon Tutup 12 Oz Datar', $html);
        $this->assertStringNotContainsString('H-018', $html);
    }

    public function test_an_empty_product_name_falls_back_instead_of_printing_a_blank_line(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['product_name'] = '';

        $preview = app(CalculateInvoicePreview::class)->calculate($payload);
        $html = view('pdf.invoices.preview', ['preview' => $preview])->render();

        $this->assertStringContainsString('Tutup Strawless SJP D93', $html);
    }

    public function test_the_server_side_calculation_passes_the_new_fields_through_untouched(): void
    {
        $preview = app(CalculateInvoicePreview::class)->calculate($this->payload());
        $item = $preview['items'][0];

        // The lid keeps its own identity instead of being replaced by the
        // "Sablon ..." description.
        $this->assertSame('Tutup Strawless SJP D93', $item['product_name']);
        $this->assertSame('H-018', $item['sku']);
        $this->assertSame('Sablon Tutup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', $item['description']);

        // Amounts are still recalculated server side, never trusted from the client.
        $this->assertSame(120000.0, (float) $item['line_total']);
    }

    public function test_the_stored_invoice_document_carries_the_product_name_only(): void
    {
        // Renders the view the download actually reaches. An earlier version
        // of this test rendered pdf.invoices.show, which nothing on this path
        // uses - so it passed while the real document was still wrong.
        $invoice = $this->storedInvoice();

        $pdf = app(GenerateInvoicePdf::class);
        $method = new ReflectionMethod($pdf, 'previewFor');
        $method->setAccessible(true);
        $preview = $method->invoke($pdf, $invoice->load('items', 'customer'));

        $html = view('pdf.invoices.document', ['preview' => $preview])->render();

        // Both lines take the product name, never the "Sablon ..." text under it.
        $this->assertStringContainsString('Cup PET 12Oz Datar SJP', $html);
        $this->assertStringContainsString('Tutup Strawless SJP D93', $html);

        $this->assertStringNotContainsString('Sablon Cup 12 Oz Datar', $html);
        $this->assertStringNotContainsString('Sablon Tutup 12 Oz Datar', $html);
        $this->assertStringNotContainsString('H-009', $html);
        $this->assertStringNotContainsString('H-018', $html);
    }

    private function storedInvoice(): Invoice
    {
        $customer = Customer::query()->create(['code' => 'CUS-DOC-1', 'name' => 'PT Bahagia']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0055',
            'issue_date' => '2026-09-07',
            'due_date' => '2026-09-21',
            'status' => Invoice::STATUS_SENT,
            'subtotal' => 120000,
            'total_amount' => 120000,
        ]);
        $invoice->items()->create([
            'product_name' => 'Cup PET 12Oz Datar SJP',
            'sku' => 'H-009',
            'description' => 'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            'cup_size' => '12 Oz',
            'cup_model' => 'Datar',
            'grammage' => '8gr',
            'quantity' => 1000,
            'unit_price' => 400,
            'subtotal' => 400000,
            'total_amount' => 400000,
        ]);
        $invoice->items()->create([
            'product_name' => 'Tutup Strawless SJP D93',
            'sku' => 'H-018',
            'description' => 'Sablon Tutup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            'cup_size' => '12 Oz',
            'cup_model' => 'Datar',
            'grammage' => '8gr',
            'quantity' => 1000,
            'unit_price' => 120,
            'subtotal' => 120000,
            'total_amount' => 120000,
        ]);

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'invoice_number' => 'INV-2026-0055',
            'issue_date' => '2026-09-07',
            'issue_date_label' => '7 September 2026',
            'currency' => 'IDR',
            'customer' => ['name' => 'PT Bahagia', 'email' => '', 'phone' => '', 'address' => ''],
            'items' => [
                [
                    'name' => 'Tutup Strawless SJP D93',
                    'product_name' => 'Tutup Strawless SJP D93',
                    'sku' => 'H-018',
                    'description' => 'Sablon Tutup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
                    'note' => 'SKU: H-018',
                    'quantity' => 1000,
                    'unit' => 'Pcs',
                    'quantity_label' => '1.000 Pcs',
                    'unit_price' => 120,
                    'line_total' => 120000,
                ],
            ],
            'subtotal' => 120000,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_enabled' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'is_free_shipping' => true,
            'total_amount' => 120000,
            'dp_required_percent' => 50,
            'dp_amount' => 60000,
            'notes' => '',
            'terms' => '',
        ];
    }
}
