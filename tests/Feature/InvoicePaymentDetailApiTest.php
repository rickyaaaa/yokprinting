<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class InvoicePaymentDetailApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_payment_detail_returns_remaining_balance_information(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '+62 21 555 0198',
            'address' => 'Jl. Jenderal Sudirman No. 88',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0084',
            'issue_date' => '2026-07-23',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'production_status' => Invoice::PRODUCTION_DESIGN_ACC,
            'currency' => 'IDR',
            'subtotal' => 9000000,
            'discount_amount' => 0,
            'tax_amount' => 1000000,
            'total_amount' => 10000000,
            'dp_required_percent' => 50,
            'design_notes' => 'Logo tengah, tunggu ACC final.',
            'mockup_url' => 'https://yokprinting.id/mockup/INV-2026-0084',
            'notes' => 'Terima kasih.',
            'terms' => 'Net 14.',
        ]);
        $invoice->items()->create([
            'product_name' => 'Sablon Cup 16 Oz Oval',
            'sku' => 'CUP-16OV-8G-2S',
            'cup_size' => '16 Oz',
            'cup_model' => 'Oval',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'jenis_cetak' => '2 warna',
            'quantity' => 10000,
            'unit_price' => 900,
            'subtotal' => 9000000,
            'total_amount' => 9000000,
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-20260723-0001',
            'payment_date' => '2026-07-23',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'reference' => 'BCA-77302',
            'amount' => 4000000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);

        $response = $this->getJson(
            route('api.invoices.payment-detail.show', ['invoice' => $invoice->invoice_number]),
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-2026-0084')
            ->assertJsonPath('data.invoice.payment_status', Invoice::PAYMENT_PARTIAL)
            ->assertJsonPath('data.invoice.payment_status_label', 'Parsial')
            ->assertJsonPath('data.invoice.production_status', Invoice::PRODUCTION_DESIGN_ACC)
            ->assertJsonPath('data.invoice.production_status_label', 'ACC Mockup/Desain')
            ->assertJsonPath('data.invoice.total_amount', 10000000)
            ->assertJsonPath('data.invoice.paid_amount', 4000000)
            ->assertJsonPath('data.invoice.remaining_amount', 6000000)
            ->assertJsonPath('data.invoice.required_dp_amount', 5000000)
            ->assertJsonPath('data.invoice.design_notes', 'Logo tengah, tunggu ACC final.')
            ->assertJsonPath('data.invoice.payment_progress', 40)
            ->assertJsonPath('data.customer.name', 'PT Sinar Nusantara')
            ->assertJsonPath('data.items.0.product_name', 'Sablon Cup 16 Oz Oval')
            ->assertJsonPath('data.items.0.cup_size', '16 Oz')
            ->assertJsonPath('data.items.0.unit_price', 900)
            ->assertJsonPath('data.payments.0.payment_number', 'PAY-20260723-0001')
            ->assertJsonPath('data.payments.0.method_label', 'Transfer BCA');
    }

    public function test_overdue_invoice_payment_detail_returns_overdue_label(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Bumi Lestari',
            'email' => 'finance@bumilestari.example',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0078',
            'issue_date' => now()->subDays(14)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => 5000000,
        ]);

        $this->getJson(
            route('api.invoices.payment-detail.show', ['invoice' => $invoice->invoice_number]),
        )
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', Invoice::PAYMENT_OVERDUE)
            ->assertJsonPath('data.invoice.payment_status_label', 'Overdue')
            ->assertJsonPath('data.invoice.is_overdue', true)
            ->assertJsonPath('data.invoice.remaining_amount', 5000000);
    }

    public function test_zero_remaining_balance_overrides_stale_status_and_overdue_date(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-LUNAS',
            'name' => 'PT Lunas Sentosa',
            'email' => 'finance@lunas.example',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LUNAS-001',
            'issue_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subWeek()->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'currency' => 'IDR',
            'total_amount' => 5000000,
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-LUNAS-001',
            'payment_date' => now()->subWeek()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 5000000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->getJson(
            route('api.invoices.payment-detail.show', ['invoice' => $invoice->invoice_number]),
        )
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', Invoice::PAYMENT_PAID)
            ->assertJsonPath('data.invoice.payment_status_label', 'Lunas')
            ->assertJsonPath('data.invoice.is_overdue', false)
            ->assertJsonPath('data.invoice.remaining_amount', 0)
            ->assertJsonPath('data.invoice.payment_progress', 100);
    }

    public function test_unknown_invoice_payment_detail_returns_not_found(): void
    {
        $this->getJson('/api/invoices/INV-2026-9999/payment-detail')
            ->assertNotFound();
    }
}
