<?php

namespace Tests\Feature;

use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_through_api_endpoint(): void
    {
        $user = User::factory()->create([
            'username' => 'andi',
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
            'company_name' => 'Ruang Karya Digital',
            'role' => User::ROLE_OWNER,
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'andi',
            'password' => 'secure-password',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.username', 'andi')
            ->assertJsonPath('data.email', 'andi@ruangkarya.example')
            ->assertJsonPath('data.company_name', 'Ruang Karya Digital')
            ->assertJsonPath('auth.type', 'session')
            ->assertJsonPath('auth.remember', true);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_login_api_rejects_wrong_username(): void
    {
        User::factory()->create([
            'username' => 'andi',
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'username-salah',
            'password' => 'secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_login_api_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'username' => 'andi',
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'andi',
            'password' => 'secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_user_can_login_through_web_session_endpoint(): void
    {
        $user = User::factory()->create([
            'username' => 'andi',
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->post(route('login.store'), [
            'username' => 'andi',
            'password' => 'secure-password',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_email_cannot_be_used_as_login_identifier(): void
    {
        User::factory()->create([
            'username' => 'andi',
            'email' => 'andi@ruangkarya.example',
            'password' => Hash::make('secure-password'),
        ]);

        $this->postJson(route('api.auth.login'), [
            'username' => 'andi@ruangkarya.example',
            'password' => 'secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        $this->assertGuest();
    }

    public function test_duplicate_username_is_rejected_by_user_validation(): void
    {
        User::factory()->create(['username' => 'andi']);
        $request = new RegisterUserRequest;
        $validator = Validator::make([
            'name' => 'Andi Kedua',
            'username' => 'andi',
            'company_name' => 'YokPrinting',
            'email' => 'andi-kedua@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'terms' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('username', $validator->errors()->toArray());
    }

    public function test_login_page_uses_fresh_csrf_token_and_disables_browser_cache(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('type="text"', false)
            ->assertSee('name="username"', false)
            ->assertSee('autocomplete="username"', false)
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
