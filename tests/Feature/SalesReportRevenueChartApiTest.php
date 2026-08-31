<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportRevenueChartApiTest extends TestCase
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

    public function test_sales_report_revenue_chart_returns_monthly_dataset(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer();
        $julyInvoice = $this->createInvoice($customer, 'INV-2026-0084', '2026-07-23', 20000000);
        $this->createVerifiedPayment($julyInvoice, 'PAY-20260723-0001', 5000000);
        $this->createInvoice($customer, 'INV-2026-0099', '2026-07-21', 99000000, Invoice::STATUS_CANCELLED);

        $response = $this->getJson(route('api.reports.sales.revenue-chart'));

        $labels = array_map('strval', range(1, 31));
        $revenue = array_fill(0, 31, 0);
        $paid = array_fill(0, 31, 0);
        $target = array_fill(0, 31, 0);
        $revenue[22] = 20000000;
        $paid[22] = 5000000;
        $target[22] = 22000000;

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.date_from', '2026-07-01')
            ->assertJsonPath('data.date_to', '2026-07-31')
            ->assertJsonPath('data.labels', $labels)
            ->assertJsonPath('data.revenue', $revenue)
            ->assertJsonPath('data.paid', $paid)
            ->assertJsonPath('data.target', $target)
            ->assertJsonPath('data.totals.revenue', 20000000)
            ->assertJsonPath('data.totals.paid', 5000000)
            ->assertJsonPath('data.datasets.0.key', 'revenue')
            ->assertJsonPath('data.datasets.1.key', 'target')
            ->assertJsonPath('data.datasets.2.key', 'paid');
    }

    public function test_sales_report_revenue_chart_supports_weekly_and_yearly_periods(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer();
        $this->createInvoice($customer, 'INV-2026-0001', '2026-01-12', 5000000);
        $this->createInvoice($customer, 'INV-2026-0083', '2026-07-20', 3000000);
        $this->createInvoice($customer, 'INV-2026-0084', '2026-07-23', 20000000);
        $this->createInvoice($customer, 'INV-2025-0100', '2025-11-10', 7000000);

        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'weekly')
            ->assertJsonPath('data.date_from', '2026-07-20')
            ->assertJsonPath('data.date_to', '2026-07-26')
            ->assertJsonPath('data.labels', ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'])
            ->assertJsonPath('data.revenue', [3000000, 0, 0, 20000000, 0, 0, 0]);

        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'yearly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'yearly')
            ->assertJsonPath('data.date_from', '2026-01-01')
            ->assertJsonPath('data.date_to', '2026-12-31')
            ->assertJsonPath('data.labels', ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'])
            ->assertJsonPath('data.revenue', [5000000, 0, 0, 0, 0, 0, 23000000, 0, 0, 0, 0, 0]);
    }

    public function test_active_draft_invoices_contribute_to_the_revenue_chart(): void
    {
        // See Invoice::scopeBusinessTransaction().
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer();
        $this->createInvoice($customer, 'INV-2026-0084', '2026-07-23', 20000000);
        $this->createInvoice($customer, 'INV-2026-0085', '2026-07-23', 3000000, Invoice::STATUS_DRAFT);
        $this->createInvoice($customer, 'INV-2026-0099', '2026-07-23', 99000000, Invoice::STATUS_CANCELLED);

        $this->getJson(route('api.reports.sales.revenue-chart'))
            ->assertOk()
            ->assertJsonPath('data.revenue.22', 23000000)
            ->assertJsonPath('data.totals.revenue', 23000000);
    }

    public function test_sales_report_revenue_chart_query_is_validated(): void
    {
        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'quarterly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        string $issueDate,
        int $totalAmount,
        string $status = Invoice::STATUS_SENT,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => CarbonImmutable::parse($issueDate)->addDays(14)->toDateString(),
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }

    private function createVerifiedPayment(Invoice $invoice, string $paymentNumber, int $amount): Payment
    {
        return $invoice->payments()->create([
            'payment_number' => $paymentNumber,
            'payment_date' => $invoice->issue_date->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);
    }
}
