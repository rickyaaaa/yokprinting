<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionSyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));
    }

    public function test_role_permissions_can_be_synced_by_ids_and_codes(): void
    {
        $role = Role::factory()->create([
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);
        $invoiceView = Permission::factory()->create([
            'code' => 'invoice.view',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'view',
        ]);
        $invoiceExport = Permission::factory()->create([
            'code' => 'invoice.export',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'export',
        ]);
        $legacyPermission = Permission::factory()->create([
            'code' => 'customer.delete',
            'module' => Permission::MODULE_CUSTOMER,
            'action' => 'delete',
        ]);
        $role->permissions()->attach($legacyPermission);

        $this->putJson(route('api.roles.permissions.update', $role->code), [
            'permission_ids' => [$invoiceView->id],
            'permissions' => ['invoice.export'],
            'constraints' => [
                (string) $invoiceView->id => ['owned_branch_only' => true],
                'invoice.export' => ['max_export_days' => 90],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Role permissions synced successfully.')
            ->assertJsonPath('data.role.code', Role::CODE_FINANCE_ADMIN)
            ->assertJsonPath('data.permission_count', 2)
            ->assertJsonFragment(['code' => 'invoice.view'])
            ->assertJsonFragment(['code' => 'invoice.export']);

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $invoiceView->id,
        ]);
        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $legacyPermission->id,
        ]);

        $pivot = $role->refresh()->permissions()->where('code', 'invoice.export')->first()->pivot;
        $this->assertSame(90, $pivot->constraints['max_export_days']);
    }

    public function test_role_permissions_can_be_displayed_and_cleared(): void
    {
        $role = Role::factory()->create(['code' => 'viewer_reports']);
        $permission = Permission::factory()->create([
            'code' => 'report.view',
            'module' => Permission::MODULE_REPORT,
            'action' => 'view',
        ]);
        $role->permissions()->attach($permission);

        $this->getJson(route('api.roles.permissions.show', $role->code))
            ->assertOk()
            ->assertJsonPath('data.permission_count', 1)
            ->assertJsonPath('data.permissions.0.code', 'report.view');

        $this->putJson(route('api.roles.permissions.update', $role->code), [
            'permissions' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.permission_count', 0)
            ->assertJsonCount(0, 'data.permissions');

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_role_permission_sync_payload_is_validated(): void
    {
        $role = Role::factory()->create(['code' => 'ops']);

        $this->putJson(route('api.roles.permissions.update', $role->code), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->putJson(route('api.roles.permissions.update', $role->code), [
            'permission_ids' => [999],
            'permissions' => ['invoice.teleport'],
            'constraints' => ['invoice.teleport' => 'not-an-array'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission_ids.0', 'permissions.0', 'constraints.invoice.teleport']);
    }
}
