<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_sales_report_data_or_export(): void
    {
        $this->get(route('reports.sales.index'))->assertRedirect(route('login'));

        foreach ($this->viewRoutes() as $routeName) {
            $this->getJson(route($routeName))->assertUnauthorized();
        }

        $this->getJson(route('api.reports.sales.export'))->assertUnauthorized();
    }

    public function test_user_without_report_permissions_is_forbidden(): void
    {
        $this->actingAs($this->userWithPermissions([]));

        foreach ($this->viewRoutes() as $routeName) {
            $this->getJson(route($routeName))->assertForbidden();
        }

        $this->getJson(route('api.reports.sales.export'))->assertForbidden();
        $this->get(route('reports.sales.index'))->assertForbidden();
    }

    public function test_report_view_and_export_permissions_are_enforced_separately(): void
    {
        $this->actingAs($this->userWithPermissions(['report.view']));

        foreach ($this->viewRoutes() as $routeName) {
            $this->getJson(route($routeName))->assertOk();
        }

        $this->get(route('reports.sales.index'))->assertOk();

        $this->getJson(route('api.reports.sales.export'))->assertForbidden();

        $this->actingAs($this->userWithPermissions(['report.export']));

        $this->get(route('api.reports.sales.export'))->assertOk();
        $this->getJson(route('api.reports.sales.summary'))->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function viewRoutes(): array
    {
        return [
            'api.reports.sales.summary',
            'api.reports.sales.invoices.index',
            'api.reports.sales.revenue-chart',
        ];
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode, 2);
            $permission = Permission::factory()->create([
                'code' => $permissionCode,
                'module' => $module,
                'action' => $action,
            ]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }
}
