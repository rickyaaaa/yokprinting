<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_table_contains_role_management_fields(): void
    {
        foreach ([
            'id',
            'name',
            'code',
            'guard_name',
            'description',
            'scope',
            'status',
            'is_system',
            'sort_order',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('roles', $column),
                "Expected roles table to contain [{$column}] column.",
            );
        }
    }

    public function test_role_can_store_permissions_and_assignment_metadata(): void
    {
        $role = Role::query()->create([
            'name' => 'Admin Finance',
            'code' => Role::CODE_FINANCE_ADMIN,
            'description' => 'Mengelola invoice, pembayaran, dan laporan finance.',
            'scope' => 'Finance & laporan',
            'is_system' => true,
            'sort_order' => 20,
        ]);

        $this->assertSame(Role::CODE_FINANCE_ADMIN, $role->code);
        $this->assertSame(Role::STATUS_ACTIVE, $role->status);
        $this->assertSame('web', $role->guard_name);
        $this->assertTrue($role->is_system);
        $this->assertSame(20, $role->sort_order);
    }

    public function test_role_code_links_users_to_role_definition(): void
    {
        $role = Role::factory()->create([
            'name' => 'Operasional',
            'code' => Role::CODE_OPERATIONS,
        ]);

        $user = User::factory()->create([
            'role' => Role::CODE_OPERATIONS,
        ]);

        $this->assertTrue($role->users->contains($user));
        $this->assertTrue($user->roleDefinition->is($role));
    }

    public function test_assignable_scope_excludes_disabled_roles(): void
    {
        Role::factory()->create([
            'code' => 'limited_finance',
            'status' => Role::STATUS_LIMITED,
        ]);

        $disabled = Role::factory()->disabled()->create([
            'code' => 'legacy_viewer',
        ]);

        $this->assertFalse(Role::query()->assignable()->get()->contains($disabled));
        $this->assertSame(1, Role::query()->assignable()->count());
    }
}
