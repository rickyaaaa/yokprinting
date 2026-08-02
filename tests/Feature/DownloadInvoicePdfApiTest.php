<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoices\GeneratedInvoicePdf;
use App\Services\Invoices\GenerateInvoicePdf;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DownloadInvoicePdfApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_can_be_downloaded_by_persisted_invoice_id(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $invoice = $this->createStoredInvoice();

        $response = $this->get(
            route('api.invoices.pdf.download', [
                'invoice' => $invoice->getKey(),
            ]),
            ['Accept' => 'application/pdf'],
        );

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="invoice-inv-2026-0079.pdf"',
            )
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(
            (string) strlen($response->getContent()),
            $response->headers->get('Content-Length'),
        );

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(
            MarkInvoiceDelivered::CHANNEL_PDF_DOWNLOAD,
            $invoice->metadata['delivery']['last_channel'],
        );
    }

    public function test_unknown_invoice_id_returns_not_found(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();

        $this->getJson('/api/invoices/999999/pdf')
            ->assertNotFound();
    }

    public function test_guest_cannot_download_stored_invoice_pdf(): void
    {
        $invoice = $this->createStoredInvoice();

        $this->getJson(route('api.invoices.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]))->assertUnauthorized();

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
        $this->assertNull($invoice->sent_at);
    }

    public function test_user_without_invoice_export_permission_cannot_download_stored_invoice_pdf(): void
    {
        $invoice = $this->createStoredInvoice();
        $this->actingAsUserWithoutInvoiceExportPermission();

        $this->getJson(route('api.invoices.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]))->assertForbidden();

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
        $this->assertNull($invoice->sent_at);
    }

    public function test_preview_invoice_pdf_can_be_downloaded_from_current_form_snapshot(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();

        $response = $this->postJson(
            route('api.invoices.preview.pdf.download'),
            $this->previewPayload(),
            ['Accept' => 'application/pdf'],
        );

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="invoice-inv-2026-0079.pdf"',
            )
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    public function test_preview_pdf_ignores_all_client_totals_and_uses_server_calculations(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $capturedPreview = null;
        $this->mock(GenerateInvoicePdf::class, function ($mock) use (&$capturedPreview): void {
            $mock->shouldReceive('generatePreview')
                ->once()
                ->andReturnUsing(function (array $preview) use (&$capturedPreview): GeneratedInvoicePdf {
                    $capturedPreview = $preview;
                    $html = view('pdf.invoices.preview', ['preview' => $preview])->render();

                    $this->assertStringContainsString('IDR 3.000', $html);
                    $this->assertStringContainsString('- IDR 300', $html);
                    $this->assertStringContainsString('IDR 297', $html);
                    $this->assertStringContainsString('IDR 3.497', $html);
                    $this->assertStringContainsString('IDR 874', $html);
                    $this->assertStringContainsString('IDR 2.623', $html);

                    return new GeneratedInvoicePdf('%PDF-server-calculated', 'invoice-server-calculated.pdf');
                });
        });
        $payload = $this->previewPayload();
        $payload['items'][0]['quantity'] = 3;
        $payload['items'][0]['unit_price'] = 1000;
        $payload['items'][0]['line_total'] = 'not-a-number';
        $payload['subtotal'] = 999999999;
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 10;
        $payload['discount_amount'] = 999999999;
        $payload['tax_enabled'] = true;
        $payload['tax_rate'] = 11;
        $payload['tax_amount'] = 999999999;
        $payload['shipping_cost'] = 500;
        $payload['total_amount'] = 1;
        $payload['grand_total'] = 2;
        $payload['dp_required_percent'] = 25;
        $payload['dp_amount'] = 999999999;
        $payload['remaining_amount'] = 999999999;
        $payload['remaining_payment'] = 999999999;

        $this->postJson(
            route('api.invoices.preview.pdf.download'),
            $payload,
            ['Accept' => 'application/pdf'],
        )
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-server-calculated.pdf"')
            ->assertContent('%PDF-server-calculated');

        $this->assertIsArray($capturedPreview);
        $this->assertSame(3000.0, $capturedPreview['items'][0]['line_total']);
        $this->assertSame(3000.0, $capturedPreview['subtotal']);
        $this->assertSame(300.0, $capturedPreview['discount_amount']);
        $this->assertSame(297.0, $capturedPreview['tax_amount']);
        $this->assertSame(3497.0, $capturedPreview['total_amount']);
        $this->assertSame(3497.0, $capturedPreview['grand_total']);
        $this->assertSame(874.25, $capturedPreview['dp_amount']);
        $this->assertSame(2622.75, $capturedPreview['remaining_amount']);
        $this->assertSame(2622.75, $capturedPreview['remaining_payment']);
    }

    public function test_preview_pdf_rejects_negative_quantity_and_price(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $payload = $this->previewPayload();
        $payload['items'][0]['quantity'] = -1;
        $payload['items'][0]['unit_price'] = -1;

        $this->postJson(route('api.invoices.preview.pdf.download'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.quantity',
                'items.0.unit_price',
            ]);
    }

    public function test_preview_pdf_rejects_non_numeric_calculation_inputs(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $payload = $this->previewPayload();
        $payload['items'][0]['quantity'] = 'invalid';
        $payload['items'][0]['unit_price'] = 'invalid';
        $payload['discount_value'] = 'invalid';
        $payload['tax_rate'] = 'invalid';
        $payload['dp_required_percent'] = 'invalid';

        $this->postJson(route('api.invoices.preview.pdf.download'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.quantity',
                'items.0.unit_price',
                'discount_value',
                'tax_rate',
                'dp_required_percent',
            ]);
    }

    public function test_preview_pdf_rejects_discount_above_its_limit(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $percentagePayload = $this->previewPayload();
        $percentagePayload['discount_type'] = 'percentage';
        $percentagePayload['discount_value'] = 101;

        $this->postJson(route('api.invoices.preview.pdf.download'), $percentagePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);

        $fixedPayload = $this->previewPayload();
        $fixedPayload['discount_type'] = 'fixed';
        $fixedPayload['discount_value'] = 150001;

        $this->postJson(route('api.invoices.preview.pdf.download'), $fixedPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_fixed_discount_uses_the_same_rounded_subtotal_as_the_server_calculator(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $payload = $this->previewPayload();
        $payload['items'][0]['quantity'] = 1.000049;
        $payload['items'][0]['unit_price'] = 10000;
        $payload['discount_type'] = 'fixed';
        $payload['discount_value'] = 10000.40;

        $this->postJson(route('api.invoices.preview.pdf.download'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_preview_pdf_rejects_dp_above_grand_total(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $payload = $this->previewPayload();
        $payload['dp_required_percent'] = 101;

        $this->postJson(route('api.invoices.preview.pdf.download'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dp_required_percent']);
    }

    public function test_guest_cannot_generate_preview_invoice_pdf(): void
    {
        $this->postJson(
            route('api.invoices.preview.pdf.download'),
            $this->previewPayload(),
        )->assertUnauthorized();
    }

    public function test_user_without_invoice_export_permission_cannot_generate_preview_pdf(): void
    {
        $this->actingAsUserWithoutInvoiceExportPermission();

        $this->postJson(
            route('api.invoices.preview.pdf.download'),
            $this->previewPayload(),
        )->assertForbidden();
    }

    public function test_pdf_generation_is_rate_limited_across_stored_and_preview_endpoints(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $invoice = $this->createStoredInvoice();
        RateLimiter::for('invoice-pdf', fn (Request $request): Limit => Limit::perMinute(2)
            ->by('user:'.$request->user()->getAuthIdentifier()));
        RateLimiter::clear('user:'.$this->app['auth']->user()->getAuthIdentifier());

        $storedRoute = route('api.invoices.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]);
        $previewRoute = route('api.invoices.preview.pdf.download');

        $this->get($storedRoute, ['Accept' => 'application/pdf'])->assertOk();
        $this->postJson($previewRoute, $this->previewPayload(), ['Accept' => 'application/pdf'])->assertOk();
        $this->getJson($storedRoute)->assertTooManyRequests();
    }

    private function createStoredInvoice(): Invoice
    {
        $invoice = Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0079',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'subtotal' => 12500000,
            'total_amount' => 12500000,
        ]);
        $invoice->items()->create([
            'product_id' => 1,
            'product_name' => 'Paket Desain Identitas Brand',
            'quantity' => 1,
            'unit_price' => 12500000,
            'subtotal' => 12500000,
            'total_amount' => 12500000,
        ]);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewPayload(): array
    {
        return [
            'invoice_number' => 'INV-2026-0079',
            'issue_date_label' => '23 Juli 2026',
            'currency' => 'IDR',
            'customer' => [
                'name' => 'PT Sinar Nusantara',
                'email' => 'finance@sinarnusantara.co.id',
                'phone' => '+62 21 555 0198',
                'address' => 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan 12190',
            ],
            'items' => [
                [
                    'name' => 'Cup Injection 12Oz Datar (360ml) Natural',
                    'note' => 'SKU: H-001 - Kelipatan jumlah 500 Pcs',
                    'quantity' => 500,
                    'unit' => 'Pcs',
                    'quantity_label' => '500 Pcs',
                    'unit_price' => 300,
                    'line_total' => 150000,
                ],
            ],
            'subtotal' => 150000,
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'discount_amount' => 7500,
            'tax_enabled' => true,
            'tax_rate' => 11,
            'tax_amount' => 15675,
            'shipping_cost' => 0,
            'is_free_shipping' => false,
            'total_amount' => 158175,
            'dp_required_percent' => 50,
            'dp_amount' => 79087.5,
            'notes' => 'Terima kasih telah mempercayakan kebutuhan bisnis Anda kepada kami.',
            'terms' => 'Minimal DP 50% sebelum produksi.',
        ];
    }

    private function actingAsUserWithInvoiceExportPermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $permission = Permission::factory()->create([
            'code' => 'invoice.export',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'export',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs(User::factory()->create([
            'role' => $role->code,
        ]));
    }

    private function actingAsUserWithoutInvoiceExportPermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);

        $this->actingAs(User::factory()->create([
            'role' => $role->code,
        ]));
    }
}
