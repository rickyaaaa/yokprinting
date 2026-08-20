<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_api_modules_reject_guests_before_controller_execution(): void
    {
        $this->getJson(route('api.dashboard.financial-summary'))->assertUnauthorized();
        $this->postJson(route('api.customers.store'), [])->assertUnauthorized();
        $this->postJson(route('api.products.store'), [])->assertUnauthorized();
        $this->postJson(route('api.invoices.drafts.store'), [])->assertUnauthorized();
        $this->getJson(route('api.payments.history.index'))->assertUnauthorized();
        $this->getJson(route('api.reports.gross-profit.index'))->assertUnauthorized();
    }

    public function test_authenticated_user_without_module_permission_is_forbidden(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->getJson(route('api.dashboard.financial-summary'))->assertForbidden();
        $this->postJson(route('api.customers.store'), [])->assertForbidden();
        $this->get(route('customers.index'))->assertForbidden();
    }

    public function test_browser_security_headers_are_present(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('onclick=', $response->getContent());
    }
}
