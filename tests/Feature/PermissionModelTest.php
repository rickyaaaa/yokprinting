<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_table_contains_access_control_fields(): void
    {
        foreach ([
            'id',
            'name',
            'code',
            'module',
            'action',
            'guard_name',
            'description',
            'risk_level',
            'is_system',
            'sort_order',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('permissions', $column),
                "Expected permissions table to contain [{$column}] column.",
            );
        }

        foreach (['role_id', 'permission_id', 'constraints'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('permission_role', $column),
                "Expected permission_role table to contain [{$column}] column.",
            );
        }
    }

    public function test_permission_can_store_module_action_and_risk_metadata(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Export laporan penjualan',
            'module' => Permission::MODULE_REPORT,
            'action' => 'export',
            'description' => 'Mengunduh laporan penjualan untuk periode terpilih.',
            'risk_level' => Permission::RISK_MEDIUM,
            'is_system' => true,
            'sort_order' => 40,
        ]);

        $this->assertSame('report.export', $permission->code);
        $this->assertSame('web', $permission->guard_name);
        $this->assertSame(Permission::RISK_MEDIUM, $permission->risk_level);
        $this->assertTrue($permission->is_system);
        $this->assertSame(40, $permission->sort_order);
    }

    public function test_roles_can_be_linked_to_permissions_with_constraints(): void
    {
        $role = Role::factory()->create([
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);
        $permission = Permission::factory()->create([
            'code' => 'invoice.export',
            'module' => Permission::MODULE_INVOICE,
            'action' => 'export',
        ]);

        $role->permissions()->attach($permission, [
            'constraints' => ['owned_branch_only' => false],
        ]);

        $this->assertTrue($role->hasPermission('invoice.export'));
        $this->assertTrue($permission->roles->contains($role));
        $invoiceExport = $role->permissions->firstWhere('code', 'invoice.export');
        $this->assertFalse($invoiceExport->pivot->constraints['owned_branch_only']);
    }

    public function test_permission_scope_filters_by_module(): void
    {
        Permission::factory()->create([
            'code' => 'dashboard.view',
            'module' => Permission::MODULE_DASHBOARD,
            'action' => 'view',
        ]);
        Permission::factory()->highRisk()->create([
            'code' => 'role.delete',
            'module' => Permission::MODULE_ROLE,
            'action' => 'delete',
        ]);

        $this->assertSame(1, Permission::query()->forModule(Permission::MODULE_ROLE)->count());
        $this->assertSame('role.delete', Permission::query()->forModule(Permission::MODULE_ROLE)->first()->code);
    }
}
