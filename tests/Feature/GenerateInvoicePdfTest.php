<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoices\GenerateInvoicePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateInvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_an_a4_pdf_with_a_safe_filename(): void
    {
        $invoice = $this->invoice();

        $pdf = app(GenerateInvoicePdf::class)->generate($invoice);

        $this->assertSame('invoice-inv-2026-0079.pdf', $pdf->filename);
        $this->assertStringStartsWith('%PDF-', $pdf->contents);
        $this->assertGreaterThan(5000, strlen($pdf->contents));
    }

    public function test_it_can_store_the_generated_pdf_on_a_laravel_disk(): void
    {
        Storage::fake('local');
        $invoice = $this->invoice();

        $path = app(GenerateInvoicePdf::class)->store($invoice);

        $this->assertSame('invoices/invoice-inv-2026-0079.pdf', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($path));
    }

    private function invoice(): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '+62 21 555 0198',
            'address' => 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV/2026/0079',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'subtotal' => 21250000,
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'discount_amount' => 1062500,
            'tax_rate' => 11,
            'tax_amount' => 2220625,
            'total_amount' => 22408125,
            'notes' => 'Terima kasih atas kepercayaan Anda.',
            'terms' => 'Pembayaran maksimal 14 hari.',
        ]);
        $invoice->items()->createMany([
            [
                'product_id' => 1,
                'product_name' => 'Paket Desain Identitas Brand',
                'sku' => 'JSA-BRAND-01',
                'quantity' => 1,
                'unit_price' => 12500000,
                'subtotal' => 12500000,
                'total_amount' => 12500000,
            ],
            [
                'product_id' => 2,
                'product_name' => 'Website Company Profile',
                'sku' => 'JSA-WEB-03',
                'quantity' => 1,
                'unit_price' => 8750000,
                'subtotal' => 8750000,
                'total_amount' => 8750000,
            ],
        ]);

        return $invoice;
    }
}
