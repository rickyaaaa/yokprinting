<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActivityLoggerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logger_records_actor_request_subject_and_metadata(): void
    {
        $user = User::factory()->create([
            'name' => 'Maya Lestari',
            'role' => User::ROLE_FINANCE_ADMIN,
        ]);
        $role = Role::factory()->create([
            'code' => Role::CODE_FINANCE_ADMIN,
        ]);
        $request = Request::create('/api/roles/finance_admin', 'PATCH', [], [], [], [
            'REMOTE_ADDR' => '103.22.18.91',
            'HTTP_USER_AGENT' => 'InvoiceHub Test Agent',
        ]);
        $request->setUserResolver(fn (): User => $user);

        $log = app(ActivityLogger::class)->record(
            module: 'role',
            action: 'update',
            event: 'Role updated',
            description: 'Role finance diperbarui.',
            subject: $role,
            metadata: ['changed_fields' => ['scope']],
            riskLevel: ActivityLog::RISK_MEDIUM,
            request: $request,
        );

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(User::ROLE_FINANCE_ADMIN, $log->actor_role);
        $this->assertSame($role::class, $log->subject_type);
        $this->assertSame($role->id, $log->subject_id);
        $this->assertSame('103.22.18.91', $log->ip_address);
        $this->assertSame('api/roles/finance_admin', $log->metadata['path']);
        $this->assertSame(['scope'], $log->metadata['changed_fields']);
    }

    public function test_login_success_and_failure_are_logged(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => 'secret-password',
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'owner',
            'password' => 'secret-password',
        ])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'login',
            'event' => 'User logged in',
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'owner',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'module' => 'auth',
            'action' => 'login_failed',
            'risk_level' => ActivityLog::RISK_HIGH,
        ]);

        $failedLogin = ActivityLog::query()->where('action', 'login_failed')->latest('id')->firstOrFail();
        $this->assertSame('owner', $failedLogin->metadata['username']);
        $this->assertArrayNotHasKey('email', $failedLogin->metadata);
    }

    public function test_role_management_endpoint_records_activity(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));

        $response = $this->postJson(route('api.roles.store'), [
            'name' => 'Supervisor Finance',
            'code' => 'supervisor_finance',
        ])
            ->assertCreated();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'role',
            'action' => 'create',
            'event' => 'Role created',
            'subject_type' => Role::class,
            'subject_id' => $response->json('data.id'),
        ]);
    }
}
