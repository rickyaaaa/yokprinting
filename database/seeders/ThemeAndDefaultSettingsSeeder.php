<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Support\ThemeAndDefaultSettings;
use Illuminate\Database\Seeder;

class ThemeAndDefaultSettingsSeeder extends Seeder
{
    /**
     * Seed the application's default theme and invoice settings.
     */
    public function run(): void
    {
        foreach (ThemeAndDefaultSettings::rows() as $setting) {
            ApplicationSetting::query()->updateOrCreate(
                [
                    'group' => $setting['group'],
                    'key' => $setting['key'],
                ],
                $setting,
            );
        }
    }
}
