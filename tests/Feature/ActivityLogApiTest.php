<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));
    }

    public function test_activity_logs_can_be_listed_with_filters_search_and_sorting(): void
    {
        $actor = User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => 'andi@example.test',
            'role' => User::ROLE_OWNER,
        ]);

        ActivityLog::factory()->create([
            'user_id' => $actor->id,
            'actor_name' => 'Andi Pratama',
            'actor_role' => User::ROLE_OWNER,
            'module' => 'role',
            'action' => 'update',
            'event' => 'Role updated',
            'description' => 'Mengubah permission Admin Finance',
            'risk_level' => ActivityLog::RISK_MEDIUM,
            'occurred_at' => '2026-07-24 09:42:00',
        ]);
        ActivityLog::factory()->highRisk()->create([
            'actor_name' => 'Unknown',
            'actor_role' => null,
            'module' => 'auth',
            'action' => 'login_failed',
            'event' => 'Failed login attempt',
            'description' => 'Percobaan login gagal untuk finance@example.test',
            'ip_address' => '182.16.42.10',
            'occurred_at' => '2026-07-23 16:44:00',
        ]);
        ActivityLog::factory()->create([
            'module' => 'invoice',
            'action' => 'create',
            'event' => 'Invoice created',
            'risk_level' => ActivityLog::RISK_LOW,
            'occurred_at' => '2026-07-24 08:00:00',
        ]);

        $this->getJson(route('api.activity-logs.index', [
            'module' => 'role',
            'risk_level' => ActivityLog::RISK_MEDIUM,
            'search' => 'Admin Finance',
            'date_from' => '2026-07-24',
            'date_to' => '2026-07-24',
            'sort' => 'occurred_at',
            'direction' => 'desc',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor_name', 'Andi Pratama')
            ->assertJsonPath('data.0.user.email', 'andi@example.test')
            ->assertJsonPath('data.0.module', 'role')
            ->assertJsonPath('meta.filters.search', 'Admin Finance')
            ->assertJsonPath('meta.filters.risk_level', ActivityLog::RISK_MEDIUM);
    }

    public function test_activity_logs_can_be_filtered_by_user_and_include_metadata(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_FINANCE_ADMIN]);

        ActivityLog::factory()->create([
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'module' => 'payment',
            'action' => 'export',
            'event' => 'Payment history exported',
            'metadata' => ['format' => 'csv'],
        ]);

        $this->getJson(route('api.activity-logs.index', [
            'user_id' => $actor->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.metadata.format', 'csv')
            ->assertJsonPath('data.0.user_id', $actor->id);
    }

    public function test_non_owner_with_activity_log_permission_can_access_endpoint(): void
    {
        $role = Role::factory()->create([
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);
        $permission = Permission::factory()->create([
            'code' => 'activity_log.view',
            'module' => 'activity_log',
            'action' => 'view',
        ]);
        $role->permissions()->attach($permission);

        $this->actingAs(User::factory()->create([
            'role' => Role::CODE_FINANCE_ADMIN,
        ]));

        $this->getJson(route('api.activity-logs.index'))
            ->assertOk();
    }

    public function test_activity_log_query_is_validated(): void
    {
        $this->getJson(route('api.activity-logs.index', [
            'risk_level' => 'critical',
            'user_id' => 999,
            'date_from' => '2026-07-25',
            'date_to' => '2026-07-24',
            'sort' => 'email',
            'direction' => 'sideways',
            'limit' => 201,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['risk_level', 'user_id', 'date_to', 'sort', 'direction', 'limit']);
    }
}
