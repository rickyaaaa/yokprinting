<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleCrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));
    }

    public function test_roles_can_be_listed_with_search_filters_sorting_and_permission_metadata(): void
    {
        $invoiceView = Permission::factory()->create([
            'code' => 'invoice.view',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'view',
        ]);
        $finance = Role::factory()->create([
            'name' => 'Admin Finance',
            'code' => Role::CODE_FINANCE_ADMIN,
            'scope' => 'Finance & laporan',
            'sort_order' => 20,
        ]);
        $finance->permissions()->attach($invoiceView);
        User::factory()->create(['role' => Role::CODE_FINANCE_ADMIN]);

        Role::factory()->disabled()->create([
            'name' => 'Viewer Lama',
            'code' => 'legacy_viewer',
            'sort_order' => 90,
        ]);

        $response = $this->getJson(route('api.roles.index', [
            'search' => 'finance',
            'status' => Role::STATUS_ACTIVE,
            'sort' => 'sort_order',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', Role::CODE_FINANCE_ADMIN)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('meta.filters.status', Role::STATUS_ACTIVE);

        $this->assertContains('invoice.view', collect($response->json('data.0.permissions'))->pluck('code')->all());
        $this->assertContains('expense.view', collect($response->json('data.0.permissions'))->pluck('code')->all());
    }

    public function test_role_can_be_created_shown_updated_and_soft_deleted(): void
    {
        $invoiceCreate = Permission::factory()->create([
            'code' => 'invoice.create',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'create',
        ]);
        $paymentExport = Permission::factory()->create([
            'code' => 'payment.export',
            'module' => Permission::MODULE_PAYMENT,
            'action' => 'export',
        ]);

        $createResponse = $this->postJson(route('api.roles.store'), [
            'name' => 'Admin Operasional',
            'code' => 'admin_operasional',
            'description' => 'Mengelola operasional invoice dan pembayaran.',
            'scope' => 'Operasional',
            'status' => Role::STATUS_LIMITED,
            'permission_ids' => [$invoiceCreate->id],
            'permissions' => ['payment.export'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'admin_operasional')
            ->assertJsonPath('data.status', Role::STATUS_LIMITED)
            ->assertJsonCount(2, 'data.permissions');

        $roleCode = $createResponse->json('data.code');

        $this->getJson(route('api.roles.show', $roleCode))
            ->assertOk()
            ->assertJsonPath('data.scope', 'Operasional')
            ->assertJsonPath('data.permissions.0.module', Permission::MODULE_INVOICE);

        $this->patchJson(route('api.roles.update', $roleCode), [
            'name' => 'Ops Finance',
            'status' => Role::STATUS_ACTIVE,
            'permissions' => ['payment.export'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ops Finance')
            ->assertJsonPath('data.status', Role::STATUS_ACTIVE)
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonPath('data.permissions.0.code', 'payment.export');

        $this->deleteJson(route('api.roles.destroy', $roleCode))
            ->assertNoContent();

        $this->assertSoftDeleted('roles', ['code' => $roleCode]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $role = Role::factory()->system()->create([
            'code' => Role::CODE_OWNER,
        ]);

        $this->deleteJson(route('api.roles.destroy', $role->code))
            ->assertStatus(409)
            ->assertJsonPath('message', 'System roles cannot be deleted.');

        $this->assertDatabaseHas('roles', [
            'code' => Role::CODE_OWNER,
            'deleted_at' => null,
        ]);
    }

    public function test_role_code_cannot_change_while_users_are_assigned(): void
    {
        $role = Role::factory()->create(['code' => 'ops_team']);
        User::factory()->create(['role' => $role->code]);

        $this->patchJson(route('api.roles.update', $role->code), [
            'code' => 'ops_team_v2',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Kode role tidak dapat diubah selama masih dipakai user. Pindahkan user terlebih dahulu.');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'code' => 'ops_team',
        ]);
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $role = Role::factory()->create(['code' => 'warehouse_team']);
        User::factory()->create(['role' => $role->code]);

        $this->deleteJson(route('api.roles.destroy', $role->code))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Role yang masih dipakai user tidak dapat dihapus. Pindahkan user terlebih dahulu.');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);
    }

    public function test_system_flag_cannot_be_changed_through_role_api(): void
    {
        $role = Role::factory()->system()->create(['code' => Role::CODE_OWNER]);

        $this->patchJson(route('api.roles.update', $role->code), [
            'is_system' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_system');
    }

    public function test_role_payload_and_query_are_validated(): void
    {
        Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);

        $this->postJson(route('api.roles.store'), [
            'name' => '',
            'code' => Role::CODE_FINANCE_ADMIN,
            'status' => 'archived',
            'is_system' => true,
            'permission_ids' => [999],
            'permissions' => ['invoice.fly'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'status', 'is_system', 'permission_ids.0', 'permissions.0']);

        $this->getJson(route('api.roles.index', [
            'status' => 'archived',
            'sort' => 'users_count',
            'direction' => 'sideways',
            'limit' => 101,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort', 'direction', 'limit']);
    }
}
