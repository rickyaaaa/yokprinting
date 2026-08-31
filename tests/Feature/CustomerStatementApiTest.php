<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerStatementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_statement_requires_authentication(): void
    {
        $customer = $this->createCustomer();

        $this->getJson(route('api.customers.statement.show', $customer))
            ->assertUnauthorized();
    }

    public function test_customer_statement_calculates_running_balance_from_invoices_and_verified_payments(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = $this->createCustomer();

        $invoiceA = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0101',
            totalAmount: 10000000,
            createdAt: '2026-07-01 09:00:00',
        );

        $this->createPayment(
            invoice: $invoiceA,
            paymentNumber: 'PAY-20260702-0001',
            amount: 4000000,
            paymentDate: '2026-07-02',
            createdAt: '2026-07-02 10:15:00',
        );

        $invoiceB = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0102',
            totalAmount: 2500000,
            createdAt: '2026-07-03 08:30:00',
        );

        $this->createPayment(
            invoice: $invoiceB,
            paymentNumber: 'PAY-20260703-0002',
            amount: 1000000,
            paymentDate: '2026-07-03',
            createdAt: '2026-07-03 16:45:00',
        );

        $invoiceB->payments()->create([
            'payment_number' => 'PAY-20260703-0003',
            'payment_date' => '2026-07-03',
            'method' => Payment::METHOD_CASH,
            'reference' => 'PENDING-001',
            'amount' => 750000,
            'status' => Payment::STATUS_PENDING,
            'currency' => 'IDR',
            'created_at' => '2026-07-03 17:00:00',
            'updated_at' => '2026-07-03 17:00:00',
        ]);

        // An active draft is a real ledger entry - it and its verified
        // payment both belong on the statement. See
        // Invoice::scopeBusinessTransaction().
        $draftInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-DRAFT',
            totalAmount: 9000000,
            createdAt: '2026-07-04 08:00:00',
        );
        $draftInvoice->forceFill(['status' => Invoice::STATUS_DRAFT])->save();
        $this->createPayment(
            invoice: $draftInvoice,
            paymentNumber: 'PAY-20260704-DRAFT',
            amount: 1000000,
            paymentDate: '2026-07-04',
            createdAt: '2026-07-04 09:00:00',
        );

        // A cancelled invoice and its payment stay off the ledger entirely.
        $cancelledInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-CANCELLED',
            totalAmount: 50000000,
            createdAt: '2026-07-05 08:00:00',
        );
        $cancelledInvoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();
        $this->createPayment(
            invoice: $cancelledInvoice,
            paymentNumber: 'PAY-20260705-CANCELLED',
            amount: 2000000,
            paymentDate: '2026-07-05',
            createdAt: '2026-07-05 09:00:00',
        );

        $this->getJson(route('api.customers.statement.show', [
            'customer' => $customer,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.customer.code', 'CUS-STATEMENT')
            ->assertJsonPath('data.summary.opening_balance', 0)
            ->assertJsonPath('data.summary.total_debit', 21500000)
            ->assertJsonPath('data.summary.total_credit', 6000000)
            ->assertJsonPath('data.summary.outstanding_amount', 15500000)
            ->assertJsonPath('data.total_outstanding_amount', 15500000)
            ->assertJsonPath('data.transactions.0.reference_number', 'INV-2026-0101')
            ->assertJsonPath('data.transactions.0.debit', 10000000)
            ->assertJsonPath('data.transactions.0.credit', 0)
            ->assertJsonPath('data.transactions.0.running_balance', 10000000)
            ->assertJsonPath('data.transactions.1.reference_number', 'PAY-20260702-0001')
            ->assertJsonPath('data.transactions.1.debit', 0)
            ->assertJsonPath('data.transactions.1.credit', 4000000)
            ->assertJsonPath('data.transactions.1.running_balance', 6000000)
            ->assertJsonPath('data.transactions.2.reference_number', 'INV-2026-0102')
            ->assertJsonPath('data.transactions.2.running_balance', 8500000)
            ->assertJsonPath('data.transactions.3.reference_number', 'PAY-20260703-0002')
            ->assertJsonPath('data.transactions.3.running_balance', 7500000)
            ->assertJsonPath('data.transactions.4.reference_number', 'INV-2026-DRAFT')
            ->assertJsonPath('data.transactions.4.debit', 9000000)
            ->assertJsonPath('data.transactions.4.running_balance', 16500000)
            ->assertJsonPath('data.transactions.5.reference_number', 'PAY-20260704-DRAFT')
            ->assertJsonPath('data.transactions.5.credit', 1000000)
            ->assertJsonPath('data.transactions.5.running_balance', 15500000)
            ->assertJsonCount(6, 'data.transactions');
    }

    public function test_customer_statement_uses_opening_balance_for_filtered_period(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = $this->createCustomer();
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0201',
            totalAmount: 8000000,
            createdAt: '2026-06-28 09:00:00',
        );

        $this->createPayment(
            invoice: $invoice,
            paymentNumber: 'PAY-20260705-0001',
            amount: 3000000,
            paymentDate: '2026-07-05',
            createdAt: '2026-07-05 11:30:00',
        );

        $this->getJson(route('api.customers.statement.show', [
            'customer' => $customer,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.opening_balance', 8000000)
            ->assertJsonPath('data.summary.total_debit', 0)
            ->assertJsonPath('data.summary.total_credit', 3000000)
            ->assertJsonPath('data.summary.outstanding_amount', 5000000)
            ->assertJsonPath('data.transactions.0.reference_number', 'PAY-20260705-0001')
            ->assertJsonPath('data.transactions.0.running_balance', 5000000);
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'code' => 'CUS-STATEMENT',
            'name' => 'PT Statement Printing',
            'email' => 'finance@statement.example',
            'phone' => '+62 21 555 0101',
            'address' => 'Jl. Ledger No. 10',
        ]);
    }

    private function createInvoice(Customer $customer, string $invoiceNumber, int $totalAmount, string $createdAt): Invoice
    {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => $invoiceNumber,
            'issue_date' => substr($createdAt, 0, 10),
            'due_date' => '2026-08-01',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);

        $invoice->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $invoice;
    }

    private function createPayment(
        Invoice $invoice,
        string $paymentNumber,
        int $amount,
        string $paymentDate,
        string $createdAt,
    ): Payment {
        $payment = $invoice->payments()->create([
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'method' => Payment::METHOD_TRANSFER_BCA,
            'reference' => 'BCA-'.$paymentNumber,
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => $createdAt,
        ]);

        $payment->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $payment;
    }
}
