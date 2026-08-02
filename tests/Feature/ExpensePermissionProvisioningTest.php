<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
