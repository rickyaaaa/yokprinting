<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use Database\Seeders\ThemeAndDefaultSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ThemeAndDefaultSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_settings_table_contains_theme_and_default_fields(): void
    {
        foreach ([
            'id',
            'group',
            'key',
            'value',
            'type',
            'label',
            'description',
            'is_public',
            'sort_order',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('application_settings', $column),
                "Expected application_settings table to contain [{$column}] column.",
            );
        }
    }

    public function test_theme_and_invoice_default_settings_are_seeded(): void
    {
        $this->seed(ThemeAndDefaultSettingsSeeder::class);

        $this->assertDatabaseCount('application_settings', 11);

        $sage = ApplicationSetting::query()
            ->where('group', 'theme')
            ->where('key', 'palette.sage')
            ->sole();

        $this->assertTrue($sage->is_public);
        $this->assertSame('palette', $sage->type);
        $this->assertSame('#52772c', $sage->value['primary']);

        $tax = ApplicationSetting::query()
            ->where('group', 'invoice')
            ->where('key', 'default_tax_rate')
            ->sole();

        $this->assertFalse($tax->is_public);
        $this->assertSame(11, $tax->value['rate']);
    }

    public function test_theme_and_default_settings_seeder_is_idempotent(): void
    {
        $this->seed(ThemeAndDefaultSettingsSeeder::class);
        $this->seed(ThemeAndDefaultSettingsSeeder::class);

        $this->assertDatabaseCount('application_settings', 11);
    }
}
