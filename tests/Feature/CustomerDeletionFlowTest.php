<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDeletionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_soft_deletes_customer_and_preserves_invoice_history(): void
    {
        $user = $this->userWithPermissions(['customer.view', 'customer.delete']);
        $customer = Customer::query()->create([
            'code' => 'CUS-DEL-001',
            'name' => 'PT Pelanggan Berinvoice',
            'email' => 'history@example.test',
            'status' => Customer::STATUS_ACTIVE,
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'invoice_number' => 'INV-HISTORY-001',
            'issue_date' => '2026-08-03',
            'due_date' => '2026-08-10',
            'total_amount' => 275000,
        ]);
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'recorded_by' => $user->id,
            'payment_number' => 'PAY-HISTORY-001',
            'payment_date' => '2026-08-04',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 100000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('api.customers.destroy', $customer))
            ->assertOk()
            ->assertJsonPath('data.invoice_count', 1)
            ->assertJsonPath('data.history_preserved', true)
            ->assertJsonPath('data.code', 'CUS-DEL-001');

        $this->assertSoftDeleted($customer);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-HISTORY-001',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
        ]);
        $this->assertSame('PT Pelanggan Berinvoice', $invoice->refresh()->customer?->name);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('PT Pelanggan Berinvoice');
        $this->getJson(route('api.customers.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $customer->id]);
    }

    public function test_guest_and_user_without_permission_cannot_delete_customer(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-DEL-002',
            'name' => 'PT Pelanggan Terlindungi',
            'email' => 'protected@example.test',
        ]);

        $this->deleteJson(route('api.customers.destroy', $customer))
            ->assertUnauthorized();

        $this->actingAs($this->userWithPermissions([]))
            ->deleteJson(route('api.customers.destroy', $customer))
            ->assertForbidden();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_button_is_only_rendered_for_authorized_users(): void
    {
        Customer::query()->create([
            'code' => 'CUS-DEL-003',
            'name' => 'PT Uji Tombol Hapus',
            'email' => 'button@example.test',
        ]);

        $this->actingAs($this->userWithPermissions(['customer.view', 'customer.delete']))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('delete-customer-button')
            ->assertSee('confirm-delete-customer')
            ->assertSee('Invoice, pembayaran, dan relasi historis tidak akan dihapus.');

        $this->actingAs($this->userWithPermissions(['customer.view']))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('delete-customer-button')
            ->assertDontSee('confirm-delete-customer');
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode, 2);
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => 'Hapus pelanggan',
                    'module' => $module,
                    'action' => $action,
                    'guard_name' => 'web',
                    'risk_level' => Permission::RISK_HIGH,
                    'is_system' => true,
                    'sort_order' => 1,
                ],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }
}
