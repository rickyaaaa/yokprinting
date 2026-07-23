<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use Database\Seeders\SalesReportDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_demo_seeder_creates_invoice_dataset(): void
    {
        $this->seed(SalesReportDemoSeeder::class);

        $this->assertDatabaseCount('customers', 5);
        $this->assertDatabaseCount('products', 4);
        $this->assertDatabaseCount('invoices', 5);
        $this->assertDatabaseCount('invoice_items', 6);
        $this->assertDatabaseCount('payments', 4);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-0084',
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'subtotal' => 18000000,
            'discount_amount' => 1530000,
            'tax_amount' => 1980000,
            'total_amount' => 18450000,
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_number' => 'PAY-20260724-0001',
            'amount' => 8000000,
            'status' => Payment::STATUS_VERIFIED,
            'method' => Payment::METHOD_TRANSFER_BCA,
        ]);

        $paidInvoice = Invoice::query()
            ->where('invoice_number', 'INV-2026-0075')
            ->firstOrFail();

        $this->assertSame(Invoice::PAYMENT_PAID, $paidInvoice->payment_status);
        $this->assertNotNull($paidInvoice->paid_at);
    }

    public function test_sales_report_demo_seeder_is_idempotent(): void
    {
        $this->seed(SalesReportDemoSeeder::class);
        $this->seed(SalesReportDemoSeeder::class);

        $this->assertSame(5, Customer::query()->count());
        $this->assertSame(4, Product::query()->count());
        $this->assertSame(5, Invoice::query()->count());
        $this->assertSame(6, InvoiceItem::query()->count());
        $this->assertSame(4, Payment::query()->count());
    }
}
