<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadInvoicePdfApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_can_be_downloaded_by_invoice_number(): void
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

        $response = $this->get(
            route('api.invoices.pdf.download', [
                'invoice' => $invoice->invoice_number,
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

    public function test_unknown_invoice_number_returns_not_found(): void
    {
        $this->getJson('/api/invoices/INV-2026-9999/pdf')
            ->assertNotFound();
    }
}
