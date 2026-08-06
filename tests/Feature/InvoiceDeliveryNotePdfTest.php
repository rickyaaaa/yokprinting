<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDeliveryNotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_delivery_note_returns_true_only_for_ready_and_completed_statuses(): void
    {
        $invoice = Invoice::factory()->create([
            'production_status' => Invoice::PRODUCTION_DRAFT,
        ]);
        $this->assertFalse($invoice->canGenerateDeliveryNote());

        $invoice->update(['production_status' => Invoice::PRODUCTION_AWAITING_DP]);
        $this->assertFalse($invoice->canGenerateDeliveryNote());

        $invoice->update(['production_status' => Invoice::PRODUCTION_DESIGN_ACC]);
        $this->assertFalse($invoice->canGenerateDeliveryNote());

        $invoice->update(['production_status' => Invoice::PRODUCTION_IN_PRODUCTION]);
        $this->assertFalse($invoice->canGenerateDeliveryNote());

        $invoice->update(['production_status' => Invoice::PRODUCTION_READY_FOR_PICKUP]);
        $this->assertTrue($invoice->canGenerateDeliveryNote());

        $invoice->update(['production_status' => Invoice::PRODUCTION_COMPLETED]);
        $this->assertTrue($invoice->canGenerateDeliveryNote());
    }

    public function test_delivery_note_number_is_stable_and_persisted(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2026-0088',
            'delivery_note_number' => null,
        ]);

        $number1 = $invoice->deliveryNoteNumber();
        $this->assertSame('SJ-2026-0088', $number1);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'delivery_note_number' => 'SJ-2026-0088',
        ]);

        $invoice->refresh();
        $number2 = $invoice->deliveryNoteNumber();
        $this->assertSame('SJ-2026-0088', $number2);
    }

    public function test_guest_cannot_download_delivery_note_pdf(): void
    {
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_READY_FOR_PICKUP);

        $this->getJson(route('api.invoices.delivery-note.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]))->assertUnauthorized();
    }

    public function test_user_without_export_permission_cannot_download_delivery_note_pdf(): void
    {
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_READY_FOR_PICKUP);
        $this->actingAsUserWithoutInvoiceExportPermission();

        $this->getJson(route('api.invoices.delivery-note.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]))->assertForbidden();
    }

    public function test_delivery_note_pdf_forbidden_when_production_status_is_not_ready_or_completed(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_IN_PRODUCTION);

        $response = $this->getJson(route('api.invoices.delivery-note.pdf.download', [
            'invoice' => $invoice->getKey(),
        ]));

        $response->assertForbidden();
    }

    public function test_delivery_note_pdf_can_be_downloaded_when_ready_for_pickup(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_READY_FOR_PICKUP);

        $response = $this->get(
            route('api.invoices.delivery-note.pdf.download', [
                'invoice' => $invoice->getKey(),
            ]),
            ['Accept' => 'application/pdf'],
        );

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="surat-jalan-sj-2026-0099.pdf"',
            )
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_delivery_note_pdf_can_be_downloaded_when_completed(): void
    {
        $this->actingAsUserWithInvoiceExportPermission();
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_COMPLETED);

        $response = $this->get(
            route('api.invoices.delivery-note.pdf.download', [
                'invoice' => $invoice->getKey(),
            ]),
            ['Accept' => 'application/pdf'],
        );

        $response->assertOk();
    }

    public function test_delivery_note_view_contains_no_pricing_tax_discount_hpp_or_payment_amounts(): void
    {
        $invoice = $this->createInvoiceWithStatus(Invoice::PRODUCTION_READY_FOR_PICKUP);
        $invoice->load(['customer', 'items']);
        $invoice->deliveryNoteNumber();

        $html = view('pdf.invoices.delivery-note', ['invoice' => $invoice])->render();

        // Customer details, invoice reference, and delivery note number
        $this->assertStringContainsString('SURAT JALAN', $html);
        $this->assertStringContainsString('SJ-2026-0099', $html);
        $this->assertStringContainsString('INV-2026-0099', $html);
        $this->assertStringContainsString('PT Kopi Bahagia', $html);
        $this->assertStringContainsString('081234567890', $html);
        $this->assertStringContainsString('Jl. Merdeka No. 10', $html);

        // Product item details
        $this->assertStringContainsString('Cup Injection 12Oz Datar', $html);
        $this->assertStringContainsString('1.000', $html);
        $this->assertStringContainsString('Pcs', $html);

        // Signatures
        $this->assertStringContainsString('Penerima Barang,', $html);
        $this->assertStringContainsString('Pengirim / Kurir,', $html);
        $this->assertStringContainsString('Hormat Kami,', $html);

        // EXCLUSIONS: No pricing/monetary keywords or values
        $this->assertStringNotContainsString('Harga', $html);
        $this->assertStringNotContainsString('Subtotal', $html);
        $this->assertStringNotContainsString('Pajak', $html);
        $this->assertStringNotContainsString('Diskon', $html);
        $this->assertStringNotContainsString('HPP', $html);
        $this->assertStringNotContainsString('Total', $html);
        $this->assertStringNotContainsString('DP', $html);
        $this->assertStringNotContainsString('Sisa', $html);
        $this->assertStringNotContainsString('IDR', $html);
        $this->assertStringNotContainsString('Rp', $html);
    }

    private function createInvoiceWithStatus(string $productionStatus): Invoice
    {
        $customer = Customer::factory()->create([
            'name' => 'PT Kopi Bahagia',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 10, Tangerang',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0099',
            'issue_date' => '2026-08-04',
            'due_date' => '2026-08-18',
            'subtotal' => 1500000,
            'total_amount' => 1500000,
            'production_status' => $productionStatus,
        ]);
        $invoice->items()->create([
            'product_name' => 'Cup Injection 12Oz Datar',
            'quantity' => 1000,
            'unit' => 'Pcs',
            'unit_price' => 1500,
            'subtotal' => 1500000,
            'total_amount' => 1500000,
        ]);

        return $invoice;
    }

    private function actingAsUserWithInvoiceExportPermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $permission = Permission::factory()->create([
            'code' => 'invoice.export',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'export',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs(User::factory()->create([
            'role' => $role->code,
        ]));
    }

    private function actingAsUserWithoutInvoiceExportPermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);

        $this->actingAs(User::factory()->create([
            'role' => $role->code,
        ]));
    }
}
