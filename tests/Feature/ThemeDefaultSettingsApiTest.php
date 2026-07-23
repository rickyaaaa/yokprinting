<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDefaultSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_default_settings_endpoint_returns_seeded_defaults(): void
    {
        $this->getJson(route('api.settings.theme-defaults.show'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.theme.default_palette', 'sage')
            ->assertJsonPath('data.theme.invoice_template', 'professional')
            ->assertJsonPath('data.theme.palettes.0.key', 'sage')
            ->assertJsonPath('data.theme.palettes.0.primary', '#52772c')
            ->assertJsonPath('data.invoice_defaults.invoice_prefix', 'INV')
            ->assertJsonPath('data.invoice_defaults.default_tax_rate', 11)
            ->assertJsonPath('data.invoice_defaults.default_due_days', 14)
            ->assertJsonPath('data.invoice_defaults.numbering_reset', 'yearly');

        $this->assertDatabaseCount('application_settings', 11);
    }

    public function test_theme_default_settings_can_be_updated(): void
    {
        $this->putJson(route('api.settings.theme-defaults.update'), [
            'default_palette' => 'ocean',
            'invoice_template' => 'modern',
            'invoice_prefix' => 'YK',
            'default_tax_rate' => 10.5,
            'default_due_days' => 21,
            'reminder_days_before_due' => 5,
            'numbering_reset' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.theme.default_palette', 'ocean')
            ->assertJsonPath('data.theme.invoice_template', 'modern')
            ->assertJsonPath('data.invoice_defaults.invoice_prefix', 'YK')
            ->assertJsonPath('data.invoice_defaults.default_tax_rate', 10.5)
            ->assertJsonPath('data.invoice_defaults.default_due_days', 21)
            ->assertJsonPath('data.invoice_defaults.reminder_days_before_due', 5)
            ->assertJsonPath('data.invoice_defaults.numbering_reset', 'monthly');

        $prefix = ApplicationSetting::query()
            ->where('group', 'invoice')
            ->where('key', 'prefix')
            ->sole();

        $this->assertSame('YK', $prefix->value['prefix']);
    }

    public function test_theme_default_settings_update_payload_is_validated(): void
    {
        $this->putJson(route('api.settings.theme-defaults.update'), [
            'default_palette' => 'purple',
            'invoice_template' => 'classic',
            'invoice_prefix' => str_repeat('A', 21),
            'default_tax_rate' => 101,
            'default_due_days' => -1,
            'reminder_days_before_due' => 366,
            'numbering_reset' => 'daily',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'default_palette',
                'invoice_template',
                'invoice_prefix',
                'default_tax_rate',
                'default_due_days',
                'reminder_days_before_due',
                'numbering_reset',
            ]);
    }
}
