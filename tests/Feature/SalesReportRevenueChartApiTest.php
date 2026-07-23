<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportRevenueChartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sales_report_revenue_chart_returns_monthly_dataset(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer();
        $februaryInvoice = $this->createInvoice($customer, 'INV-2026-0020', '2026-02-12', 10000000);
        $this->createVerifiedPayment($februaryInvoice, 'PAY-20260212-0001', 6000000);
        $julyInvoice = $this->createInvoice($customer, 'INV-2026-0084', '2026-07-23', 20000000);
        $this->createVerifiedPayment($julyInvoice, 'PAY-20260723-0001', 5000000);
        $this->createInvoice($customer, 'INV-2026-0099', '2026-07-21', 99000000, Invoice::STATUS_CANCELLED);

        $response = $this->getJson(route('api.reports.sales.revenue-chart'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.labels', ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'])
            ->assertJsonPath('data.revenue', [10000000, 0, 0, 0, 0, 20000000])
            ->assertJsonPath('data.paid', [6000000, 0, 0, 0, 0, 5000000])
            ->assertJsonPath('data.target', [11000000, 0, 0, 0, 0, 22000000])
            ->assertJsonPath('data.totals.revenue', 30000000)
            ->assertJsonPath('data.totals.paid', 11000000)
            ->assertJsonPath('data.datasets.0.key', 'revenue')
            ->assertJsonPath('data.datasets.1.key', 'target')
            ->assertJsonPath('data.datasets.2.key', 'paid');
    }

    public function test_sales_report_revenue_chart_supports_quarterly_and_yearly_periods(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer();
        $this->createInvoice($customer, 'INV-2026-0001', '2026-01-12', 5000000);
        $this->createInvoice($customer, 'INV-2026-0084', '2026-07-23', 20000000);
        $this->createInvoice($customer, 'INV-2025-0100', '2025-11-10', 7000000);

        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'quarterly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'quarterly')
            ->assertJsonPath('data.labels', ['Q4 2025', 'Q1 2026', 'Q2 2026', 'Q3 2026'])
            ->assertJsonPath('data.revenue', [7000000, 5000000, 0, 20000000]);

        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'yearly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'yearly')
            ->assertJsonPath('data.labels', ['2024', '2025', '2026'])
            ->assertJsonPath('data.revenue', [0, 7000000, 25000000]);
    }

    public function test_sales_report_revenue_chart_query_is_validated(): void
    {
        $this->getJson(route('api.reports.sales.revenue-chart', ['period' => 'weekly']))
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
