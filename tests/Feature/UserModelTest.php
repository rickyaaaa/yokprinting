<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_contains_business_security_and_session_fields(): void
    {
        foreach ([
            'username',
            'company_name',
            'role',
            'status',
            'phone',
            'job_title',
            'avatar_path',
            'last_login_at',
            'last_login_ip',
            'security_preferences',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "Expected users table to contain [{$column}] column.",
            );
        }
    }

    public function test_user_can_be_created_with_role_status_and_security_preferences(): void
    {
        $user = User::query()->create([
            'name' => 'Andi Pratama',
            'username' => 'andi',
            'email' => 'andi@example.test',
            'password' => 'secret-password',
            'company_name' => 'Ruang Karya Digital',
            'role' => User::ROLE_FINANCE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'phone' => '+62 812 3456 7890',
            'job_title' => 'Finance Manager',
            'last_login_at' => '2026-07-24 09:00:00',
            'last_login_ip' => '103.22.18.91',
            'security_preferences' => [
                'login_alerts' => true,
                'two_factor_required' => false,
            ],
        ]);

        $this->assertSame(User::ROLE_FINANCE_ADMIN, $user->role);
        $this->assertSame('andi', $user->username);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertSame('Finance Manager', $user->job_title);
        $this->assertSame('103.22.18.91', $user->last_login_ip);
        $this->assertTrue($user->security_preferences['login_alerts']);
        $this->assertNotNull($user->last_login_at);
        $this->assertNotSame('secret-password', $user->password);
    }

    public function test_user_factory_can_create_suspended_user(): void
    {
        $user = User::factory()->suspended()->create();

        $this->assertSame(User::STATUS_SUSPENDED, $user->status);
        $this->assertFalse($user->isActive());
    }

    public function test_username_is_required_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['username' => null]);
    }

    public function test_username_is_unique_in_the_database(): void
    {
        User::factory()->create(['username' => 'andi']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'andi']);
    }
}
