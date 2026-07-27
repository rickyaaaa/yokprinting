<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

    public function test_login_page_uses_fresh_csrf_token_and_disables_browser_cache(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_expired_login_csrf_token_redirects_back_to_login_with_helpful_message(): void
    {
        Route::post('/csrf-mismatch-probe', fn () => throw new TokenMismatchException)
            ->middleware('web');

        $this->post('/csrf-mismatch-probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Sesi login kedaluwarsa. Silakan coba login lagi.');
    }
}
