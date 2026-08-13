<?php

namespace Tests\Feature;

use App\Mail\InvoiceSentMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInvoiceEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_new_invoice_is_sent_using_database_recipient_and_content(): void
    {
        Mail::fake();
        $this->actingAsUserWithInvoiceUpdatePermission();
        $customer = $this->createCustomer(
            name: 'PT Data Database',
            email: 'finance.database@example.com',
        );
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-DB-NEW-0001',
            totalAmount: 22408125,
            notes: 'Catatan resmi dari database.',
        );

        $this->postJson(
            route('api.invoices.send', ['invoice' => $invoice->getKey()]),
            [
                'recipient' => 'attacker@example.com',
                'invoice_number' => 'INV-PREVIEW-PALSU',
                'total_amount' => 1,
                'customer' => ['name' => 'Pelanggan Preview Palsu'],
                'items' => [['name' => 'Produk Preview Palsu', 'total_amount' => 1]],
            ],
        )
            ->assertOk()
            ->assertJsonPath('message', 'Invoice berhasil dikirim.')
            ->assertJsonPath('data.invoice_id', $invoice->getKey())
            ->assertJsonPath('data.invoice_number', 'INV-DB-NEW-0001')
            ->assertJsonPath('data.recipient', 'finance.database@example.com')
            ->assertJsonPath('data.status', Invoice::STATUS_SENT);

        Mail::assertSent(InvoiceSentMail::class, function (InvoiceSentMail $mail) use ($invoice): bool {
            $rendered = $mail->render();

            $this->assertTrue($mail->hasTo('finance.database@example.com'));
            $this->assertFalse($mail->hasTo('attacker@example.com'));
            $this->assertTrue($mail->invoice->is($invoice));
            $this->assertStringContainsString('INV-DB-NEW-0001', $rendered);
            $this->assertStringContainsString('PT Data Database', $rendered);
            $this->assertStringContainsString('Produk Database', $rendered);
            $this->assertStringContainsString('22.408.125', $rendered);
            $this->assertStringContainsString('Catatan resmi dari database.', $rendered);
            $this->assertStringNotContainsString('INV-PREVIEW-PALSU', $rendered);
            $this->assertStringNotContainsString('Pelanggan Preview Palsu', $rendered);
            $this->assertStringNotContainsString('Produk Preview Palsu', $rendered);

            return true;
        });

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(
            MarkInvoiceDelivered::CHANNEL_EMAIL,
            $invoice->metadata['delivery']['last_channel'],
        );
        $this->assertSame(
            'finance.database@example.com',
            $invoice->metadata['delivery']['last_recipient'],
        );
    }

    public function test_saved_invoice_is_marked_sent_when_whatsapp_is_opened(): void
    {
        $this->actingAsUserWithInvoiceUpdatePermission();
        $customer = $this->createCustomer();
        $customer->update(['phone' => '0812-9900-1188']);
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-WA-0001',
            totalAmount: 1250000,
        );

        $this->postJson(route('api.invoices.send-whatsapp', ['invoice' => $invoice]), [
            'purpose' => 'invoice',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Invoice ditandai terkirim via WhatsApp.')
            ->assertJsonPath('data.status', Invoice::STATUS_SENT)
            ->assertJsonPath('data.recipient', '081299001188')
            ->assertJsonPath('data.purpose', 'invoice')
            ->assertJsonPath('data.whatsapp_url', fn (string $url): bool => str_starts_with($url, 'https://wa.me/6281299001188?text='));

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(MarkInvoiceDelivered::CHANNEL_WHATSAPP, $invoice->metadata['delivery']['last_channel']);
        $this->assertSame('081299001188', $invoice->metadata['delivery']['last_recipient']);
    }

    public function test_whatsapp_delivery_requires_a_valid_customer_phone_number(): void
    {
        $this->actingAsUserWithInvoiceUpdatePermission();
        $invoice = $this->createInvoice(
            customer: $this->createCustomer(),
            invoiceNumber: 'INV-WA-NO-PHONE',
            totalAmount: 500000,
        );

        $this->postJson(route('api.invoices.send-whatsapp', ['invoice' => $invoice]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient']);

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_unsaved_preview_number_cannot_select_an_old_database_invoice(): void
    {
        Mail::fake();
        $this->actingAsUserWithInvoiceUpdatePermission();
        $customer = $this->createCustomer();
        $oldInvoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-OLD-0001',
            totalAmount: 9000000,
        );

        $this->postJson('/api/invoices/INV-OLD-0001/send', [
            'invoice_number' => 'INV-NEW-UNSAVED',
            'total_amount' => 1,
        ])->assertNotFound();

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $oldInvoice->refresh()->status);
        $this->assertNull($oldInvoice->sent_at);
    }

    public function test_unsaved_new_invoice_flow_requires_a_persisted_id_before_whatsapp_or_stored_pdf(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $view = file_get_contents(resource_path('views/invoices/preview.blade.php'));
        $deliveryApi = file_get_contents(resource_path('js/services/invoice-delivery-api.js'));

        $this->assertStringContainsString('this.persistedInvoiceId !== null', $script);
        $this->assertStringContainsString('if (!this.canSendWhatsApp)', $script);
        $this->assertStringContainsString('invoiceId: this.invoiceId', $script);
        $this->assertStringContainsString('sendInvoiceWhatsApp', $script);
        $this->assertStringContainsString('downloadInvoicePdf(this.invoiceId)', $script);
        $this->assertStringContainsString('downloadInvoicePreviewPdf(this.preview)', $script);
        $this->assertStringContainsString('sendingWhatsApp || !canSendWhatsApp', $view);
        $this->assertStringNotContainsString('recipient: this.recipient', $script);
        $this->assertStringNotContainsString('JSON.stringify({ recipient })', $deliveryApi);
    }

    public function test_invoice_without_database_customer_email_is_not_sent(): void
    {
        Mail::fake();
        $this->actingAsUserWithInvoiceUpdatePermission();
        $customer = $this->createCustomer(email: null);
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-NO-EMAIL-0001',
            totalAmount: 500000,
        );

        $this->postJson(route('api.invoices.send', ['invoice' => $invoice->getKey()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient']);

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
        $this->assertNull($invoice->sent_at);
    }

    public function test_guest_cannot_send_a_persisted_invoice(): void
    {
        Mail::fake();
        $customer = $this->createCustomer();
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-GUEST-0001',
            totalAmount: 500000,
        );

        $this->postJson(route('api.invoices.send', ['invoice' => $invoice->getKey()]))
            ->assertUnauthorized();

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_user_without_invoice_update_permission_cannot_send_a_persisted_invoice(): void
    {
        Mail::fake();
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $this->actingAs(User::factory()->create(['role' => $role->code]));
        $customer = $this->createCustomer();
        $invoice = $this->createInvoice(
            customer: $customer,
            invoiceNumber: 'INV-FORBIDDEN-0001',
            totalAmount: 500000,
        );

        $this->postJson(route('api.invoices.send', ['invoice' => $invoice->getKey()]))
            ->assertForbidden();

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    private function createCustomer(
        string $name = 'PT Sinar Nusantara',
        ?string $email = 'finance@sinarnusantara.co.id',
    ): Customer {
        return Customer::query()->create([
            'code' => 'CUS-'.str()->random(8),
            'name' => $name,
            'email' => $email,
        ]);
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        int $totalAmount,
        ?string $notes = null,
    ): Invoice {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => $invoiceNumber,
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
            'notes' => $notes,
        ]);
        $invoice->items()->create([
            'product_id' => 1,
            'product_name' => 'Produk Database',
            'quantity' => 500,
            'unit_price' => $totalAmount / 500,
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
        ]);

        return $invoice;
    }

    private function actingAsUserWithInvoiceUpdatePermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $permission = Permission::factory()->create([
            'code' => 'invoice.update',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'update',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs(User::factory()->create([
            'role' => $role->code,
        ]));
    }
}
