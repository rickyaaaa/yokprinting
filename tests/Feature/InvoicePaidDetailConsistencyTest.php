<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaidDetailConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_page_hides_outstanding_warning_reminder_and_payment_form(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::query()->create([
            'code' => 'CUS-LUNAS',
            'name' => 'PT Lunas Sentosa',
            'email' => 'finance@lunas.example',
            'phone' => '081234567890',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LUNAS-001',
            'issue_date' => today()->subMonth(),
            'due_date' => today()->subWeek(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'currency' => 'IDR',
            'total_amount' => 5000000,
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-LUNAS-001',
            'payment_date' => today()->subWeek(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 5000000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->get(route('payments.invoices.show', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertSee('Lunas')
            ->assertSee('payment-complete-state')
            ->assertSee('Invoice sudah lunas')
            ->assertSee('Sisa tagihan Rp0. Pembayaran tambahan tidak dapat dicatat.')
            ->assertDontSee('Outstanding')
            ->assertDontSee('Kirim pengingat')
            ->assertDontSee('Kirim via WA')
            ->assertDontSee('Simpan pembayaran')
            ->assertDontSee('Gunakan sisa')
            ->assertDontSee('payment-validation-summary');
    }
}
