<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_through_api_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
            'company_name' => 'Ruang Karya Digital',
            'role' => User::ROLE_OWNER,
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => 'andi@ruangkarya.example',
            'password' => 'secure-password',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.email', 'andi@ruangkarya.example')
            ->assertJsonPath('data.company_name', 'Ruang Karya Digital')
            ->assertJsonPath('auth.type', 'session')
            ->assertJsonPath('auth.remember', true);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_login_api_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => 'andi@ruangkarya.example',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_api_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => 'andi@ruangkarya.example',
            'password' => 'secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_through_web_session_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'andi@ruangkarya.example',
            'password' => 'secure-password',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }
}
