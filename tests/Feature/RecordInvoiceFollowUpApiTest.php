<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordInvoiceFollowUpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_record_a_follow_up(): void
    {
        $invoice = $this->createInvoice();

        $this->postJson(route('api.invoices.follow-up.store', ['invoice' => $invoice->invoice_number]), [
            'note' => 'Telepon finance',
        ])->assertUnauthorized();

        $this->assertNull($invoice->refresh()->last_follow_up_at);
    }

    public function test_user_without_invoice_update_permission_cannot_record_a_follow_up(): void
    {
        $invoice = $this->createInvoice();
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->postJson(route('api.invoices.follow-up.store', ['invoice' => $invoice->invoice_number]), [
            'note' => 'Telepon finance',
        ])->assertForbidden();

        $this->assertNull($invoice->refresh()->last_follow_up_at);
    }

    public function test_follow_up_can_be_recorded_with_a_note(): void
    {
        $invoice = $this->createInvoice();
        $user = User::factory()->create(['role' => User::ROLE_OWNER, 'name' => 'Maya Lestari']);
        $this->actingAs($user);

        $response = $this->postJson(route('api.invoices.follow-up.store', ['invoice' => $invoice->invoice_number]), [
            'note' => 'Telepon finance, janji bayar besok',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.last_follow_up_note', 'Telepon finance, janji bayar besok')
            ->assertJsonPath('data.last_follow_up_by', 'Maya Lestari');

        $invoice->refresh();
        $this->assertNotNull($invoice->last_follow_up_at);
        $this->assertSame('Telepon finance, janji bayar besok', $invoice->last_follow_up_note);
        $this->assertSame($user->getKey(), $invoice->last_follow_up_by);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'invoice',
            'action' => 'follow_up_recorded',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id' => $invoice->getKey(),
        ]);

        $response->assertJsonMissingValidationErrors('note');
    }

    public function test_follow_up_can_be_recorded_without_a_note(): void
    {
        $invoice = $this->createInvoice();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.follow-up.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertJsonPath('data.last_follow_up_note', null);

        $this->assertNotNull($invoice->refresh()->last_follow_up_at);
    }

    public function test_note_longer_than_500_characters_is_rejected(): void
    {
        $invoice = $this->createInvoice();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.follow-up.store', ['invoice' => $invoice->invoice_number]), [
            'note' => str_repeat('a', 501),
        ])->assertJsonValidationErrors('note');

        $this->assertNull($invoice->refresh()->last_follow_up_at);
    }

    public function test_unknown_invoice_number_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.follow-up.store', ['invoice' => 'INV-2026-9999']))
            ->assertNotFound();
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
            'invoice_number' => 'INV-2026-0401',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5000000,
        ]);
    }
}
