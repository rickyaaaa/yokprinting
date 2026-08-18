<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_cancel_an_invoice(): void
    {
        $invoice = $this->createInvoice();

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnauthorized();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_user_without_invoice_update_permission_cannot_cancel_an_invoice(): void
    {
        $invoice = $this->createInvoice();
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertForbidden();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_invoice_can_be_cancelled_with_a_reason(): void
    {
        $invoice = $this->createInvoice();
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($user);

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]), [
            'reason' => 'Customer batal order.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED)
            ->assertJsonPath('data.cancellation_reason', 'Customer batal order.');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame($user->getKey(), $invoice->cancelled_by);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'invoice',
            'action' => 'cancelled',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id' => $invoice->getKey(),
        ]);
    }

    public function test_already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invoice_with_a_payment_cannot_be_cancelled(): void
    {
        $invoice = $this->createInvoice();
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260725-0001',
            'payment_date' => '2026-07-25',
            'method' => Payment::METHOD_CASH,
            'amount' => 1000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_invoice_with_completed_production_cannot_be_cancelled(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['production_status' => Invoice::PRODUCTION_COMPLETED])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function createInvoice(): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0501',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5000000,
        ]);
    }
}
