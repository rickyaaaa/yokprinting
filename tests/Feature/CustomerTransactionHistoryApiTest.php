<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTransactionHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_transaction_history_returns_summary_invoices_and_payments(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-920',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinar.example.com',
        ]);

        $partialInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0084',
            issueDate: now()->subDays(2)->toDateString(),
            dueDate: now()->addDays(5)->toDateString(),
            totalAmount: 10000000,
            paymentStatus: Invoice::PAYMENT_PARTIAL,
        );
        $partialInvoice->payments()->create([
            'payment_number' => 'PAY-20260723-0001',
            'payment_date' => now()->subDay()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'reference' => 'BCA-77302',
            'amount' => 4000000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);
        $partialInvoice->payments()->create([
            'payment_number' => 'PAY-20260723-0002',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_CASH,
            'reference' => 'CSH-901',
            'amount' => 1000000,
            'status' => Payment::STATUS_PENDING,
            'currency' => 'IDR',
        ]);

        $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0078',
            issueDate: now()->subDays(14)->toDateString(),
            dueDate: now()->subDays(2)->toDateString(),
            totalAmount: 5000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );

        $this->getJson(route('api.customers.transactions.index', $customer))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.customer.code', 'CUS-920')
            ->assertJsonPath('data.customer.activity_status', Customer::ACTIVITY_NEVER_ORDERED)
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.total_amount', 15000000)
            ->assertJsonPath('data.summary.paid_amount', 4000000)
            ->assertJsonPath('data.summary.outstanding_amount', 11000000)
            ->assertJsonPath('data.summary.overdue_count', 1)
            ->assertJsonPath('data.invoices.0.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.invoices.0.paid_amount', 4000000)
            ->assertJsonPath('data.invoices.0.outstanding_amount', 6000000)
            ->assertJsonPath('data.invoices.0.payments.0.payment_number', 'PAY-20260723-0002')
            ->assertJsonPath('data.invoices.0.payments.1.method_label', 'Transfer BCA')
            ->assertJsonPath('data.invoices.1.payment_status', Invoice::PAYMENT_OVERDUE)
            ->assertJsonPath('data.invoices.1.payment_status_label', 'Overdue');
    }

    public function test_unknown_customer_transaction_history_returns_not_found(): void
    {
        $this->getJson('/api/customers/9999/transactions')
            ->assertNotFound();
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        string $issueDate,
        string $dueDate,
        int $totalAmount,
        string $paymentStatus,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => Invoice::STATUS_SENT,
            'payment_status' => $paymentStatus,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }
}
