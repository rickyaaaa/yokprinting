<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpensePermissionProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_migration_provisions_expense_permissions_for_finance_admin_idempotently(): void
    {
        $expected = ['expense.create', 'expense.delete', 'expense.update', 'expense.view'];
        $this->assertSame(4, Permission::query()->where('module', Permission::MODULE_EXPENSE)->count());

        $financeRole = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $this->assertSame(
            $expected,
            $financeRole->permissions()->where('module', Permission::MODULE_EXPENSE)->pluck('code')->sort()->values()->all(),
        );

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(4, Permission::query()->where('module', Permission::MODULE_EXPENSE)->count());
        $this->assertSame(
            $expected,
            $financeRole->refresh()->permissions()->where('module', Permission::MODULE_EXPENSE)->pluck('code')->sort()->values()->all(),
        );
    }

    public function test_migration_updates_active_permission_idempotently_and_rollback_preserves_it(): void
    {
        $permission = Permission::query()->where('code', 'expense.view')->firstOrFail();
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $createdAt = now()->subYear()->startOfSecond();
        $constraints = json_encode(['scope' => 'preexisting'], JSON_THROW_ON_ERROR);

        $permission->forceFill([
            'name' => 'Permission Pengeluaran Lama',
            'created_at' => $createdAt,
        ])->save();
        DB::table('permission_role')
            ->where('role_id', $role->getKey())
            ->where('permission_id', $permission->getKey())
            ->update(['constraints' => $constraints, 'created_at' => $createdAt]);

        $migration = require database_path('migrations/2026_08_02_010000_provision_expense_permissions.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->getKey(),
            'code' => 'expense.view',
            'name' => 'Lihat Pengeluaran',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'constraints' => $constraints,
        ]);
        $this->assertSame(
            $createdAt->toDateTimeString(),
            Permission::query()->findOrFail($permission->getKey())->created_at->toDateTimeString(),
        );
    }

    public function test_migration_restores_soft_deleted_permission_before_assigning_finance_admin(): void
    {
        $permission = Permission::query()->where('code', 'expense.update')->firstOrFail();
        $permission->delete();
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);

        $this->assertFalse($role->permissions()->where('code', 'expense.update')->exists());

        $migration = require database_path('migrations/2026_08_02_010000_provision_expense_permissions.php');
        $migration->up();
        $migration->up();

        $restored = Permission::query()->where('code', 'expense.update')->firstOrFail();
        $this->assertSame($permission->getKey(), $restored->getKey());
        $this->assertNull($restored->deleted_at);
        $this->assertSame('Ubah Pengeluaran', $restored->name);
        $this->assertTrue($role->refresh()->permissions()->whereKey($restored->getKey())->exists());
        $this->assertSame(4, Permission::query()->where('module', Permission::MODULE_EXPENSE)->count());

        $migration->down();
        $this->assertDatabaseHas('permissions', [
            'id' => $restored->getKey(),
            'code' => 'expense.update',
            'deleted_at' => null,
        ]);
    }
}
