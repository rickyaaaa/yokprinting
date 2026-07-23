<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileSettingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_profile_settings_page_is_marked_as_backend_connected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/company-profile')
            ->assertOk()
            ->assertSee('Terhubung backend')
            ->assertSee('Perubahan profil, default invoice, tema, dan logo sudah dikirim ke backend.')
            ->assertSee('simpan ke storage aplikasi');
    }

    public function test_company_profile_settings_frontend_uses_backend_api_contracts(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('/api/company-profile', $script);
        $this->assertStringContainsString('/api/company-profile/logo', $script);
        $this->assertStringContainsString('/api/settings/theme-defaults', $script);
        $this->assertStringContainsString('companyProfilePayload', $script);
        $this->assertStringContainsString('themeDefaultPayload', $script);
        $this->assertStringContainsString('normalizeServerErrors', $script);
    }
}
