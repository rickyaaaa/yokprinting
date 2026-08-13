<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceProductionStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_invoice_production_status(): void
    {
        $invoice = $this->createInvoice();

        $this->patchJson($this->updateUrl($invoice), [
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
        ])->assertUnauthorized();

        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->refresh()->production_status);
    }

    public function test_user_without_invoice_update_permission_is_forbidden(): void
    {
        $invoice = $this->createInvoice();
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
            ])
            ->assertForbidden();

        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->refresh()->production_status);
    }

    public function test_authorized_user_can_update_status_and_change_is_audited(): void
    {
        $invoice = $this->createInvoice();
        $invoice->payments()->create([
            'payment_number' => 'PAY-DP-AUDIT-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 500000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Status produksi berhasil diperbarui.')
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.production_status', Invoice::PRODUCTION_IN_PRODUCTION)
            ->assertJsonPath('data.production_status_label', 'Proses Sablon/Cetak');

        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $invoice->refresh()->production_status);

        $log = ActivityLog::query()
            ->where('action', 'production_status_updated')
            ->firstOrFail();

        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($invoice->id, $log->subject_id);
        $this->assertSame(Invoice::PRODUCTION_DRAFT, $log->metadata['before']);
        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $log->metadata['after']);
    }

    public function test_unknown_production_status_is_rejected(): void
    {
        $invoice = $this->createInvoice();

        $this->actingAs(User::factory()->create())
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => 'shipping_without_validation',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_status');

        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->refresh()->production_status);
    }

    public function test_unpaid_invoice_cannot_be_marked_completed(): void
    {
        $invoice = $this->createInvoice(paymentStatus: Invoice::PAYMENT_PARTIAL);

        $this->actingAs(User::factory()->create())
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_COMPLETED,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_status');

        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->refresh()->production_status);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'production_status_updated']);
    }

    public function test_minimum_dp_is_required_before_production_or_delivery_can_progress(): void
    {
        $invoice = $this->createInvoice(paymentStatus: Invoice::PAYMENT_UNPAID);

        $this->actingAs(User::factory()->create())
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_status');

        $invoice->payments()->create([
            'payment_number' => 'PAY-DP-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 500000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);

        $this->patchJson($this->updateUrl($invoice), [
            'production_status' => Invoice::PRODUCTION_READY_FOR_PICKUP,
        ])
            ->assertOk()
            ->assertJsonPath('data.production_status', Invoice::PRODUCTION_READY_FOR_PICKUP);
    }

    public function test_paid_invoice_can_be_marked_completed(): void
    {
        $invoice = $this->createInvoice(paymentStatus: Invoice::PAYMENT_PAID);
        $invoice->payments()->create([
            'payment_number' => 'PAY-LUNAS-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 1000000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('data.production_status_label', 'Lunas & Selesai');

        $this->assertSame(Invoice::PRODUCTION_COMPLETED, $invoice->refresh()->production_status);
    }

    public function test_cancelled_invoice_production_status_cannot_be_changed(): void
    {
        $invoice = $this->createInvoice(status: Invoice::STATUS_CANCELLED);

        $this->actingAs(User::factory()->create())
            ->patchJson($this->updateUrl($invoice), [
                'production_status' => Invoice::PRODUCTION_AWAITING_DP,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_status');

        $this->assertSame(Invoice::PRODUCTION_DRAFT, $invoice->refresh()->production_status);
    }

    public function test_payment_detail_page_uses_the_stored_invoice_and_status(): void
    {
        $invoice = $this->createInvoice(productionStatus: Invoice::PRODUCTION_DESIGN_ACC);
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('payments.invoices.show', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('PT Status Produksi')
            ->assertSee('ACC Mockup/Desain')
            ->assertSee('Update status produksi');
    }

    private function createInvoice(
        string $status = Invoice::STATUS_SENT,
        string $paymentStatus = Invoice::PAYMENT_UNPAID,
        string $productionStatus = Invoice::PRODUCTION_DRAFT,
    ): Invoice {
        $customer = Customer::query()->create([
            'code' => 'CUS-STATUS',
            'name' => 'PT Status Produksi',
            'email' => 'produksi@example.test',
            'phone' => '081234567890',
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-STATUS-0001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'production_status' => $productionStatus,
            'currency' => 'IDR',
            'subtotal' => 1000000,
            'total_amount' => 1000000,
            'dp_required_percent' => 50,
        ]);
    }

    private function updateUrl(Invoice $invoice): string
    {
        return route('api.invoices.production-status.update', [
            'invoice' => $invoice->invoice_number,
        ]);
    }
}
