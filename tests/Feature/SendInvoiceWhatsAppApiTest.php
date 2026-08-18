<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoices\MarkInvoiceDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendInvoiceWhatsAppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_invoice_is_marked_sent_when_whatsapp_is_opened(): void
    {
        $this->actingAsUserWithInvoiceUpdatePermission();
        $customer = $this->createCustomer(phone: '0812-9900-1188');
        $invoice = $this->createInvoice($customer, 'INV-WA-0001', 1250000);

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
        $invoice = $this->createInvoice($this->createCustomer(), 'INV-WA-NO-PHONE', 500000);

        $this->postJson(route('api.invoices.send-whatsapp', ['invoice' => $invoice]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient']);

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_guest_and_user_without_permission_cannot_send_via_whatsapp(): void
    {
        $invoice = $this->createInvoice($this->createCustomer(phone: '081299001188'), 'INV-WA-AUTH', 500000);

        $this->postJson(route('api.invoices.send-whatsapp', $invoice))->assertUnauthorized();

        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $this->actingAs(User::factory()->create(['role' => $role->code]));
        $this->postJson(route('api.invoices.send-whatsapp', $invoice))->assertForbidden();

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_email_delivery_endpoint_has_been_removed(): void
    {
        $this->actingAsUserWithInvoiceUpdatePermission();
        $invoice = $this->createInvoice($this->createCustomer(phone: '081299001188'), 'INV-NO-EMAIL', 500000);

        $this->postJson("/api/invoices/{$invoice->getKey()}/send")->assertNotFound();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_unsaved_invoice_flow_requires_a_persisted_id_before_whatsapp_or_stored_pdf(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $view = file_get_contents(resource_path('views/invoices/preview.blade.php'));
        $deliveryApi = file_get_contents(resource_path('js/services/invoice-delivery-api.js'));

        $this->assertStringContainsString('this.persistedInvoiceId !== null', $script);
        $this->assertStringContainsString('if (!this.canSendWhatsApp)', $script);
        $this->assertStringContainsString('sendInvoiceWhatsApp', $script);
        $this->assertStringContainsString('sendingWhatsApp || !canSendWhatsApp', $view);
        $this->assertStringContainsString('/send-whatsapp', $deliveryApi);
        $this->assertStringNotContainsString('/send`', $deliveryApi);
    }

    private function createCustomer(?string $phone = null): Customer
    {
        return Customer::query()->create([
            'code' => 'CUS-'.str()->random(8),
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => $phone,
        ]);
    }

    private function createInvoice(Customer $customer, string $invoiceNumber, int $totalAmount): Invoice
    {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => $invoiceNumber,
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
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

        $this->actingAs(User::factory()->create(['role' => $role->code]));
    }
}
