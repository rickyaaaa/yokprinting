<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportSummaryApiTest extends TestCase
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

    public function test_sales_report_summary_calculates_period_kpis(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-23 12:00:00'));

        $customer = $this->createCustomer('PT Sinar Nusantara');

        $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0060',
            issueDate: '2026-06-25',
            dueDate: '2026-07-05',
            totalAmount: 10000000,
            paymentStatus: Invoice::PAYMENT_PAID,
        );

        $paidInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0084',
            issueDate: '2026-07-23',
            dueDate: '2026-07-30',
            totalAmount: 10000000,
            paymentStatus: Invoice::PAYMENT_PAID,
        );
        $this->createVerifiedPayment($paidInvoice, 'PAY-20260723-0001', 10000000);

        $partialOverdueInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0078',
            issueDate: '2026-07-10',
            dueDate: '2026-07-20',
            totalAmount: 20000000,
            paymentStatus: Invoice::PAYMENT_PARTIAL,
        );
        $this->createVerifiedPayment($partialOverdueInvoice, 'PAY-20260723-0002', 8000000);

        $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0082',
            issueDate: '2026-07-20',
            dueDate: '2026-08-02',
            totalAmount: 5000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );

        $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0099',
            issueDate: '2026-07-21',
            dueDate: '2026-07-31',
            totalAmount: 99000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
            status: Invoice::STATUS_CANCELLED,
        );

        $response = $this->getJson(route('api.reports.sales.summary', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period.date_from', '2026-07-01')
            ->assertJsonPath('data.period.date_to', '2026-07-31')
            ->assertJsonPath('data.total_sales', 35000000)
            ->assertJsonPath('data.invoice_count', 3)
            ->assertJsonPath('data.paid_invoice_count', 1)
            ->assertJsonPath('data.outstanding_invoice_count', 2)
            ->assertJsonPath('data.paid_amount', 18000000)
            ->assertJsonPath('data.outstanding_amount', 17000000)
            ->assertJsonPath('data.overdue_amount', 12000000)
            ->assertJsonPath('data.average_invoice_amount', 11666666.67)
            ->assertJsonPath('data.comparison.previous_total_sales', 10000000)
            ->assertJsonPath('data.comparison.growth_percentage', 250)
            ->assertJsonPath('data.cards.0.key', 'total_sales')
            ->assertJsonPath('data.cards.3.key', 'outstanding_amount')
            ->assertJsonPath('data.status_breakdown.0.status', Invoice::PAYMENT_PAID)
            ->assertJsonPath('data.status_breakdown.0.count', 1)
            ->assertJsonPath('data.status_breakdown.2.status', Invoice::PAYMENT_UNPAID)
            ->assertJsonPath('data.status_breakdown.2.count', 1)
            ->assertJsonPath('data.status_breakdown.3.status', Invoice::PAYMENT_OVERDUE)
            ->assertJsonPath('data.status_breakdown.3.count', 1);
    }

    public function test_sales_report_summary_query_is_validated(): void
    {
        $this->getJson(route('api.reports.sales.summary', [
            'date_from' => '2026-07-31',
            'date_to' => '2026-07-01',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    private function createCustomer(string $name): Customer
    {
        return Customer::query()->create([
            'code' => 'CUS-001',
            'name' => $name,
            'email' => str($name)->slug()->append('@example.com')->toString(),
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

    private function createVerifiedPayment(Invoice $invoice, string $paymentNumber, int $amount): Payment
    {
        return $invoice->payments()->create([
            'payment_number' => $paymentNumber,
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);
    }
}
