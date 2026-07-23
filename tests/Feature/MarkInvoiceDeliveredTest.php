<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Invoices\MarkInvoiceDelivered;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MarkInvoiceDeliveredTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_preserves_first_sent_time_and_source_metadata_across_deliveries(): void
    {
        CarbonImmutable::setTestNow('2026-07-23 10:00:00');
        $invoice = Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0079',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'metadata' => ['source' => 'invoice-draft-api'],
        ]);
        $service = app(MarkInvoiceDelivered::class);

        $service->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_EMAIL,
            'billing@example.com',
        );
        CarbonImmutable::setTestNow('2026-07-23 11:00:00');
        $updatedInvoice = $service->handle(
            $invoice,
            MarkInvoiceDelivered::CHANNEL_PDF_DOWNLOAD,
        );

        $this->assertSame(Invoice::STATUS_SENT, $updatedInvoice->status);
        $this->assertSame(
            '2026-07-23 10:00:00',
            $updatedInvoice->sent_at->format('Y-m-d H:i:s'),
        );
        $this->assertSame('invoice-draft-api', $updatedInvoice->metadata['source']);
        $this->assertSame(
            MarkInvoiceDelivered::CHANNEL_PDF_DOWNLOAD,
            $updatedInvoice->metadata['delivery']['last_channel'],
        );
        $this->assertCount(2, $updatedInvoice->metadata['delivery_events']);
        $this->assertSame(
            'billing@example.com',
            $updatedInvoice->metadata['delivery_events'][0]['recipient'],
        );
    }

    public function test_it_rejects_unknown_delivery_channels(): void
    {
        $invoice = Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0080',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(MarkInvoiceDelivered::class)->handle($invoice, 'unknown');
    }
}
