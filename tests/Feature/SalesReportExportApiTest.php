<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportExportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sales_report_export_downloads_excel_compatible_csv(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer('PT Sinar Nusantara');
        $brand = $this->createProduct('JSA-BRAND-01', 'Paket desain brand refresh', 'Jasa desain');
        $catalog = $this->createProduct('PRN-CATALOG-01', 'Cetak katalog premium', 'Cetak premium');

        $brandInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0084',
            issueDate: '2026-07-23',
            dueDate: '2026-07-30',
            totalAmount: 18450000,
            paymentStatus: Invoice::PAYMENT_PAID,
        );
        $this->createItem($brandInvoice, $brand, 18450000);

        $catalogInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0082',
            issueDate: '2026-07-20',
            dueDate: '2026-08-02',
            totalAmount: 12750000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );
        $this->createItem($catalogInvoice, $catalog, 12750000);

        $cancelledInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0099',
            issueDate: '2026-07-21',
            dueDate: '2026-07-31',
            totalAmount: 99000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
            status: Invoice::STATUS_CANCELLED,
        );
        $this->createItem($cancelledInvoice, $brand, 99000000);

        $response = $this->get(route('api.reports.sales.export', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'category' => 'Jasa desain',
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="laporan-penjualan-2026-07-23.csv"');

        $content = $response->getContent();

        $this->assertStringStartsWith("\u{FEFF}Pelanggan,Email,Produk,Kategori,Invoice", $content);
        $this->assertStringContainsString('"PT Sinar Nusantara",finance@sinarnusantara.co.id,"Paket desain brand refresh","Jasa desain",INV-2026-0084,2026-07-23,2026-07-30,18450000,"Belum tersedia",Lunas', $content);
        $this->assertStringNotContainsString('INV-2026-0082', $content);
        $this->assertStringNotContainsString('INV-2026-0099', $content);
    }

    public function test_sales_report_export_query_is_validated(): void
    {
        $this->getJson(route('api.reports.sales.export', [
            'date_from' => '2026-07-31',
            'date_to' => '2026-07-01',
            'status' => 'unknown',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
                'status',
            ]);
    }

    private function createCustomer(string $name): Customer
    {
        return Customer::query()->create([
            'code' => 'CUS-001',
            'name' => $name,
            'email' => 'finance@sinarnusantara.co.id',
        ]);
    }

    private function createProduct(string $sku, string $name, string $category): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'description' => $name,
            'unit' => 'paket',
            'price' => 1000000,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        string $issueDate,
        string $dueDate,
        int $totalAmount,
        string $paymentStatus,
        string $status = Invoice::STATUS_SENT,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }

    private function createItem(Invoice $invoice, Product $product, int $amount): void
    {
        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'quantity' => 1,
            'unit' => $product->unit,
            'unit_price' => $amount,
            'subtotal' => $amount,
            'total_amount' => $amount,
        ]);
    }
}
