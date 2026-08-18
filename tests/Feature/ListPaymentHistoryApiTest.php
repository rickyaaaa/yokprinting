<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class ListPaymentHistoryApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_payment_history_endpoint_returns_payments(): void
    {
        $invoice = $this->createInvoice('PT Sinar Nusantara', 'INV-2026-0084');
        $this->createPayment(
            invoice: $invoice,
            paymentNumber: 'PAY-20260726-0001',
            paymentDate: '2026-07-26',
            method: Payment::METHOD_TRANSFER_BCA,
            amount: 4000000,
            status: Payment::STATUS_VERIFIED,
            reference: 'BCA-77302',
        );

        $response = $this->getJson(route('api.payments.history.index'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payment_number', 'PAY-20260726-0001')
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.0.customer.name', 'PT Sinar Nusantara')
            ->assertJsonPath('data.0.method_label', 'Transfer BCA')
            ->assertJsonPath('data.0.amount', 4000000)
            ->assertJsonPath('data.0.status_label', 'Terverifikasi');
    }

    public function test_payment_history_endpoint_filters_searches_and_sorts(): void
    {
        $sinarInvoice = $this->createInvoice('PT Sinar Nusantara', 'INV-2026-0084');
        $bumiInvoice = $this->createInvoice('PT Bumi Lestari', 'INV-2026-0078');
        $this->createPayment(
            invoice: $sinarInvoice,
            paymentNumber: 'PAY-20260726-0001',
            paymentDate: '2026-07-26',
            method: Payment::METHOD_TRANSFER_BCA,
            amount: 4000000,
            status: Payment::STATUS_VERIFIED,
            reference: 'BCA-77302',
        );
        $this->createPayment(
            invoice: $bumiInvoice,
            paymentNumber: 'PAY-20260722-0002',
            paymentDate: '2026-07-22',
            method: Payment::METHOD_CASH,
            amount: 2500000,
            status: Payment::STATUS_PENDING,
            reference: 'CSH-1024',
        );

        $this->getJson(route('api.payments.history.index', [
            'status' => Payment::STATUS_PENDING,
            'method' => Payment::METHOD_CASH,
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payment_number', 'PAY-20260722-0002');

        $this->getJson(route('api.payments.history.index', ['q' => 'Sinar']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0084');

        $this->getJson(route('api.payments.history.index', [
            'date_from' => '2026-07-25',
            'date_to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payment_number', 'PAY-20260726-0001');

        $this->getJson(route('api.payments.history.index', [
            'sort' => 'amount',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.payment_number', 'PAY-20260722-0002')
            ->assertJsonPath('data.1.payment_number', 'PAY-20260726-0001');
    }

    public function test_payment_history_query_is_validated(): void
    {
        $this->getJson(route('api.payments.history.index', [
            'status' => 'unknown',
            'method' => 'wire',
            'date_from' => '2026-07-31',
            'date_to' => '2026-07-01',
            'sort' => 'created_at',
            'direction' => 'sideways',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'method',
                'date_to',
                'sort',
                'direction',
            ]);
    }

    private function createInvoice(string $customerName, string $invoiceNumber): Invoice
    {
        $nextNumber = Customer::query()->count() + 1;
        $customer = Customer::query()->create([
            'code' => 'CUS-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
            'name' => $customerName,
            'email' => str($customerName)->slug()->append('@example.com')->toString(),
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => '2026-07-20',
            'due_date' => '2026-08-03',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'currency' => 'IDR',
            'total_amount' => 10000000,
        ]);
    }

    private function createPayment(
        Invoice $invoice,
        string $paymentNumber,
        string $paymentDate,
        string $method,
        int $amount,
        string $status,
        string $reference,
    ): Payment {
        return $invoice->payments()->create([
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'method' => $method,
            'reference' => $reference,
            'amount' => $amount,
            'status' => $status,
            'currency' => 'IDR',
            'verified_at' => $status === Payment::STATUS_VERIFIED ? now() : null,
        ]);
    }
}
