<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logs_table_contains_audit_fields(): void
    {
        foreach ([
            'id',
            'user_id',
            'actor_name',
            'actor_role',
            'module',
            'action',
            'event',
            'description',
            'subject_type',
            'subject_id',
            'ip_address',
            'user_agent',
            'metadata',
            'risk_level',
            'occurred_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('activity_logs', $column),
                "Expected activity_logs table to contain [{$column}] column.",
            );
        }
    }

    public function test_activity_log_can_store_actor_subject_metadata_and_risk(): void
    {
        $user = User::factory()->create([
            'name' => 'Andi Pratama',
            'role' => User::ROLE_OWNER,
        ]);
        $role = Role::factory()->create([
            'name' => 'Admin Finance',
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);

        $log = ActivityLog::query()->create([
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'module' => 'invoice',
            'action' => 'update',
            'event' => 'Invoice status updated',
            'description' => 'Mengubah status invoice menjadi terkirim.',
            'subject_type' => $role::class,
            'subject_id' => $role->id,
            'ip_address' => '103.22.18.91',
            'user_agent' => 'InvoiceHub Test Agent',
            'metadata' => [
                'role_code' => Role::CODE_FINANCE_ADMIN,
                'old_status' => Role::STATUS_ACTIVE,
                'new_status' => Role::STATUS_LIMITED,
            ],
            'risk_level' => ActivityLog::RISK_MEDIUM,
            'occurred_at' => '2026-07-24 10:30:00',
        ]);

        $this->assertTrue($log->user->is($user));
        $this->assertTrue($log->subject->is($role));
        $this->assertSame(Role::STATUS_LIMITED, $log->metadata['new_status']);
        $this->assertSame(ActivityLog::RISK_MEDIUM, $log->risk_level);
        $this->assertNotNull($log->occurred_at);
    }

    public function test_activity_log_scopes_filter_by_module_and_high_risk(): void
    {
        ActivityLog::factory()->create([
            'module' => 'auth',
            'action' => 'login',
        ]);
        ActivityLog::factory()->highRisk()->create([
            'module' => 'role',
            'action' => 'delete',
        ]);

        $this->assertSame(1, ActivityLog::query()->forModule('role')->count());
        $this->assertSame(1, ActivityLog::query()->highRisk()->count());
        $this->assertSame('role', ActivityLog::query()->highRisk()->first()->module);
    }
}
