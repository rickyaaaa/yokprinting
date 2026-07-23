<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_unauthorized_for_permission_protected_api_endpoint(): void
    {
        $this->getJson(route('api.roles.index'))
            ->assertUnauthorized();
    }

    public function test_user_without_required_permission_is_forbidden(): void
    {
        $this->actingAsUserWithPermissions([]);

        $this->getJson(route('api.roles.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to access the resource.');
    }

    public function test_user_with_required_permission_can_access_endpoint(): void
    {
        $this->actingAsUserWithPermissions(['role.create']);

        $this->postJson(route('api.roles.store'), [
            'name' => 'Role Baru',
            'code' => 'role_baru',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'role_baru');
    }

    public function test_owner_role_bypasses_permission_lookup_for_bootstrap_access(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));

        $this->postJson(route('api.roles.store'), [
            'name' => 'Supervisor',
            'code' => 'supervisor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'supervisor');
    }

    public function test_inactive_user_is_forbidden_even_when_owner(): void
    {
        $this->actingAs(User::factory()->suspended()->create([
            'role' => User::ROLE_OWNER,
        ]));

        $this->getJson(route('api.roles.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not active.');
    }

    /**
     * Sign in a finance admin user with the provided permission codes.
     *
     * @param  list<string>  $permissionCodes
     */
    private function actingAsUserWithPermissions(array $permissionCodes): void
    {
        $role = Role::factory()->create([
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);

        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode, 2);

            $role->permissions()->attach(Permission::factory()->create([
                'code' => $permissionCode,
                'module' => $module,
                'action' => $action,
            ]));
        }

        $this->actingAs(User::factory()->create([
            'role' => Role::CODE_FINANCE_ADMIN,
        ]));
    }
}
