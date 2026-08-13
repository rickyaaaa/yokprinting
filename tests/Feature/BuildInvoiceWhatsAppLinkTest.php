<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoices\BuildInvoiceWhatsAppLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildInvoiceWhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_whatsapp_deep_link_with_invoice_payment_context(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '0812-9900-1188',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0084',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'total_amount' => 10000000,
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-20260723-0001',
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 4000000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
        ]);

        $link = app(BuildInvoiceWhatsAppLink::class)->build(
            $invoice,
            'https://yokprinting.id/invoices/INV-2026-0084',
        );

        $this->assertStringStartsWith('https://wa.me/6281299001188?text=', $link);
        $this->assertStringContainsString(rawurlencode('Invoice: INV-2026-0084'), $link);
        $this->assertStringContainsString(rawurlencode('DP/pembayaran diterima: Rp4.000.000'), $link);
        $this->assertStringContainsString(rawurlencode('Sisa pelunasan: Rp6.000.000'), $link);
        $this->assertStringContainsString(rawurlencode('Jatuh tempo: 6 Agustus 2026'), $link);
    }

    public function test_it_builds_a_distinct_payment_reminder_message(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'PT Reminder',
            'phone' => '0812-9900-1188',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-REMINDER-001',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'total_amount' => 1000000,
        ]);

        $link = app(BuildInvoiceWhatsAppLink::class)->build($invoice, null, true);

        $this->assertStringContainsString(rawurlencode('Pengingat pembayaran dari YokPrinting.ID:'), $link);
        $this->assertStringContainsString(rawurlencode('Mohon konfirmasi apabila pembayaran sudah dilakukan.'), $link);
    }
}
