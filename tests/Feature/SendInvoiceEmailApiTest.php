<?php

namespace Tests\Feature;

use App\Mail\InvoiceSentMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInvoiceEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_sent_and_delivery_status_is_recorded(): void
    {
        Mail::fake();
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0079',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'total_amount' => 22408125,
        ]);

        $this->postJson(
            route('api.invoices.send', ['invoice' => $invoice->invoice_number]),
            ['recipient' => 'billing@example.com'],
        )
            ->assertOk()
            ->assertJsonPath('message', 'Invoice berhasil dikirim.')
            ->assertJsonPath('data.invoice_id', 'INV-2026-0079')
            ->assertJsonPath('data.recipient', 'billing@example.com')
            ->assertJsonPath('data.status', Invoice::STATUS_SENT)
            ->assertJsonPath('data.message_id', null);

        Mail::assertSent(
            InvoiceSentMail::class,
            fn (InvoiceSentMail $mail): bool => $mail->hasTo('billing@example.com')
                && $mail->invoice->is($invoice),
        );

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(
            MarkInvoiceDelivered::CHANNEL_EMAIL,
            $invoice->metadata['delivery']['last_channel'],
        );
        $this->assertSame(
            'billing@example.com',
            $invoice->metadata['delivery']['last_recipient'],
        );
    }

    public function test_recipient_is_validated_before_sending(): void
    {
        Mail::fake();
        $invoice = Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0080',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
        ]);

        $this->postJson(
            route('api.invoices.send', ['invoice' => $invoice->invoice_number]),
            ['recipient' => 'bukan-email'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient']);

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
        $this->assertNull($invoice->sent_at);
    }

    public function test_unknown_invoice_number_returns_not_found(): void
    {
        Mail::fake();

        $this->postJson('/api/invoices/INV-2026-9999/send', [
            'recipient' => 'billing@example.com',
        ])->assertNotFound();

        Mail::assertNothingSent();
    }
}
