<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalAuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_protected_web_pages(): void
    {
        $protectedPages = [
            '/',
            '/dashboard',
            '/customers',
            '/products',
            '/invoices',
            '/invoices/create',
            '/payments/receivables',
            '/reports/sales',
            '/settings/company-profile',
            '/roles',
            '/activity-logs',
        ];

        foreach ($protectedPages as $page) {
            $this->get($page)
                ->assertRedirect(route('login'));
        }
    }

    public function test_login_remains_available_to_guests_and_register_is_disabled(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome Back');

        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');
    }

    public function test_authenticated_user_can_access_protected_web_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Workspace setelah login');
    }
}
