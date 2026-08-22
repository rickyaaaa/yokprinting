<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSalesReportInvoicesApiTest extends TestCase
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

    public function test_sales_report_invoice_endpoint_returns_filtered_rows(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer('PT Sinar Nusantara');
        $brand = $this->createProduct('JSA-BRAND-01', 'Paket desain brand refresh', 'Jasa desain');
        $catalog = $this->createProduct('PRN-CATALOG-01', 'Cetak katalog premium', 'Cetak premium');
        $website = $this->createProduct('JSA-WEB-03', 'Website company profile', 'Jasa desain');

        $paidInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0084',
            issueDate: '2026-07-23',
            dueDate: '2026-07-30',
            totalAmount: 18450000,
            paymentStatus: Invoice::PAYMENT_PAID,
        );
        $this->createItem($paidInvoice, $brand, 18450000);

        $waitingInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0082',
            issueDate: '2026-07-20',
            dueDate: '2026-08-02',
            totalAmount: 12750000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );
        $this->createItem($waitingInvoice, $catalog, 12750000);

        $overdueInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0078',
            issueDate: '2026-07-10',
            dueDate: '2026-07-20',
            totalAmount: 5600000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );
        $this->createItem($overdueInvoice, $website, 5600000);

        $cancelledInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0099',
            issueDate: '2026-07-22',
            dueDate: '2026-07-30',
            totalAmount: 99000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
            status: Invoice::STATUS_CANCELLED,
        );
        $this->createItem($cancelledInvoice, $brand, 99000000);

        $response = $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.0.customer.name', 'PT Sinar Nusantara')
            ->assertJsonPath('data.0.product', 'Paket desain brand refresh')
            ->assertJsonPath('data.0.category', 'Jasa desain')
            ->assertJsonPath('data.0.issue_date', '2026-07-23')
            ->assertJsonPath('data.0.total_amount', 18450000)
            ->assertJsonPath('data.0.status', Invoice::PAYMENT_PAID)
            ->assertJsonPath('data.1.invoice_number', 'INV-2026-0082')
            ->assertJsonPath('data.2.invoice_number', 'INV-2026-0078')
            ->assertJsonPath('data.2.status', Invoice::PAYMENT_OVERDUE)
            ->assertJsonPath('data.2.status_label', 'Overdue');
    }

    public function test_sales_report_invoice_endpoint_filters_searches_and_sorts(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $sinar = $this->createCustomer('PT Sinar Nusantara');
        $lautan = $this->createCustomer('CV Lautan Rasa', 'CUS-002');
        $brand = $this->createProduct('JSA-BRAND-01', 'Paket desain brand refresh', 'Jasa desain');
        $catalog = $this->createProduct('PRN-CATALOG-01', 'Cetak katalog premium', 'Cetak premium');

        $this->createItem(
            $this->createInvoice($sinar, 'INV-2026-0084', '2026-07-23', '2026-07-30', 18450000, Invoice::PAYMENT_PAID),
            $brand,
            18450000,
        );
        $this->createItem(
            $this->createInvoice($lautan, 'INV-2026-0082', '2026-07-20', '2026-08-02', 12750000, Invoice::PAYMENT_UNPAID),
            $catalog,
            12750000,
        );
        $this->createItem(
            $this->createInvoice($sinar, 'INV-2026-0078', '2026-07-10', '2026-07-20', 5600000, Invoice::PAYMENT_UNPAID),
            $brand,
            5600000,
        );

        $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'category' => 'Jasa desain',
            'sort' => 'issue_date',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0078')
            ->assertJsonPath('data.1.invoice_number', 'INV-2026-0084');

        $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'status' => Invoice::PAYMENT_OVERDUE,
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0078');

        $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'q' => 'katalog',
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0082');
    }

    public function test_same_day_invoices_use_invoice_number_as_a_deterministic_tiebreak(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00'));

        $customer = $this->createCustomer('PT Tiebreak');
        $product = $this->createProduct('PRN-TIE-01', 'Produk tiebreak', 'Cetak premium');

        // Same issue_date, created in this order (A before B).
        $this->createItem(
            $this->createInvoice($customer, 'INV-SORT-A', '2026-08-22', '2026-09-05', 100000, Invoice::PAYMENT_UNPAID),
            $product,
            100000,
        );
        $this->createItem(
            $this->createInvoice($customer, 'INV-SORT-B', '2026-08-22', '2026-09-05', 200000, Invoice::PAYMENT_UNPAID),
            $product,
            200000,
        );
        $this->createItem(
            $this->createInvoice($customer, 'INV-SORT-C', '2026-08-21', '2026-09-04', 300000, Invoice::PAYMENT_UNPAID),
            $product,
            300000,
        );

        // Default (issue_date desc): newest date first, and on a tie the
        // "larger" invoice_number (B > A alphabetically, matching creation
        // order here) wins, then the older date last.
        $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.invoice_number', 'INV-SORT-B')
            ->assertJsonPath('data.1.invoice_number', 'INV-SORT-A')
            ->assertJsonPath('data.2.invoice_number', 'INV-SORT-C');
    }

    public function test_sales_report_invoice_query_is_validated(): void
    {
        $this->getJson(route('api.reports.sales.invoices.index', [
            'date_from' => '2026-07-31',
            'date_to' => '2026-07-01',
            'status' => 'unknown',
            'sort' => 'created_at',
            'direction' => 'sideways',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
                'status',
                'sort',
                'direction',
            ]);
    }

    private function createCustomer(string $name, string $code = 'CUS-001'): Customer
    {
        return Customer::query()->create([
            'code' => $code,
            'name' => $name,
            'email' => str($name)->slug()->append('@example.com')->toString(),
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
