<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class ListReceivablesApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_receivables_endpoint_returns_outstanding_invoices(): void
    {
        $customer = $this->createCustomer('PT Sinar Nusantara');
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0084',
            dueDate: now()->addDays(5)->toDateString(),
            totalAmount: 10000000,
            paymentStatus: Invoice::PAYMENT_PARTIAL,
        );
        $this->createVerifiedPayment($invoice, 4000000);
        $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-2026-0085',
            dueDate: now()->addDays(7)->toDateString(),
            totalAmount: 8000000,
            paymentStatus: Invoice::PAYMENT_PAID,
        );

        $response = $this->getJson(route('api.payments.receivables.index'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.0.customer.name', 'PT Sinar Nusantara')
            ->assertJsonPath('data.0.paid_amount', 4000000)
            ->assertJsonPath('data.0.outstanding_amount', 6000000)
            ->assertJsonPath('data.0.status', 'partial')
            ->assertJsonPath('data.0.status_label', 'Parsial');
    }

    public function test_receivables_endpoint_filters_searches_and_sorts(): void
    {
        $sinar = $this->createCustomer('PT Sinar Nusantara');
        $bumi = $this->createCustomer('PT Bumi Lestari');
        $this->createInvoice(
            customer: $sinar,
            invoiceNumber: 'INV-2026-0084',
            dueDate: now()->addDays(5)->toDateString(),
            totalAmount: 10000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );
        $this->createInvoice(
            customer: $bumi,
            invoiceNumber: 'INV-2026-0078',
            dueDate: now()->subDays(3)->toDateString(),
            totalAmount: 5000000,
            paymentStatus: Invoice::PAYMENT_UNPAID,
        );

        $this->getJson(route('api.payments.receivables.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0078')
            ->assertJsonPath('data.0.status', 'overdue')
            ->assertJsonPath('data.0.status_label', 'Overdue');

        $this->getJson(route('api.payments.receivables.index', ['q' => 'Sinar']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084');

        $this->getJson(route('api.payments.receivables.index', [
            'sort' => 'outstanding',
            'direction' => 'desc',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.1.invoice_number', 'INV-2026-0078');
    }

    public function test_receivables_query_is_validated(): void
    {
        $this->getJson(route('api.payments.receivables.index', [
            'status' => 'unknown',
            'sort' => 'created_at',
            'direction' => 'sideways',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'sort',
                'direction',
            ]);
    }

    private function createCustomer(string $name): Customer
    {
        $nextNumber = Customer::query()->count() + 1;

        return Customer::query()->create([
            'code' => 'CUS-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
            'name' => $name,
            'email' => str($name)->slug()->append('@example.com')->toString(),
        ]);
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        string $dueDate,
        int $totalAmount,
        string $paymentStatus,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => $dueDate,
            'status' => Invoice::STATUS_SENT,
            'payment_status' => $paymentStatus,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }

    private function createVerifiedPayment(Invoice $invoice, int $amount): Payment
    {
        return $invoice->payments()->create([
            'payment_number' => 'PAY-20260723-0001',
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);
    }
}
