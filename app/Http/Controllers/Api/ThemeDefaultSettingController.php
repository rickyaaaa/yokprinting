<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateThemeDefaultSettingsRequest;
use App\Models\ApplicationSetting;
use App\Support\ThemeAndDefaultSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ThemeDefaultSettingController extends Controller
{
    /**
     * Display theme palettes and invoice default settings.
     */
    public function show(): JsonResponse
    {
        $this->ensureDefaultsExist();

        return response()->json([
            'status' => 'success',
            'data' => $this->settingsPayload(),
        ]);
    }

    /**
     * Update theme and invoice default settings.
     */
    public function update(UpdateThemeDefaultSettingsRequest $request): JsonResponse
    {
        $this->ensureDefaultsExist();

        foreach ($this->settingUpdates($request->validated()) as $setting) {
            ApplicationSetting::query()
                ->where('group', $setting['group'])
                ->where('key', $setting['key'])
                ->sole()
                ->update(['value' => $setting['value']]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->settingsPayload(),
            'message' => 'Theme and default settings saved successfully.',
        ]);
    }

    /**
     * Seed missing defaults without requiring a manual database seed step.
     */
    private function ensureDefaultsExist(): void
    {
        foreach (ThemeAndDefaultSettings::rows() as $setting) {
            ApplicationSetting::query()->firstOrCreate(
                [
                    'group' => $setting['group'],
                    'key' => $setting['key'],
                ],
                $setting,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(): array
    {
        $settings = ApplicationSetting::query()
            ->whereIn('group', ['theme', 'invoice'])
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->keyBy(fn (ApplicationSetting $setting): string => "{$setting->group}.{$setting->key}");

        return [
            'theme' => [
                'palettes' => $this->palettes($settings),
                'default_palette' => $settings->get('theme.default_palette')?->value['key'] ?? 'sage',
                'invoice_template' => $settings->get('theme.invoice_template')?->value['template'] ?? 'professional',
            ],
            'invoice_defaults' => [
                'invoice_prefix' => $settings->get('invoice.prefix')?->value['prefix'] ?? 'INV',
                'default_tax_rate' => (float) ($settings->get('invoice.default_tax_rate')?->value['rate'] ?? 11),
                'default_due_days' => (int) ($settings->get('invoice.default_due_days')?->value['days'] ?? 14),
                'reminder_days_before_due' => (int) ($settings->get('invoice.reminder_days_before_due')?->value['days'] ?? 3),
                'numbering_reset' => $settings->get('invoice.numbering_reset')?->value['reset'] ?? 'yearly',
            ],
        ];
    }

    /**
     * @param  Collection<string, ApplicationSetting>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function palettes(Collection $settings): array
    {
        return $settings
            ->filter(fn (ApplicationSetting $setting): bool => $setting->group === 'theme' && str_starts_with($setting->key, 'palette.'))
            ->sortBy('sort_order')
            ->map(fn (ApplicationSetting $setting): array => [
                'key' => str($setting->key)->after('palette.')->toString(),
                'label' => $setting->label,
                'description' => $setting->description,
                'primary' => $setting->value['primary'],
                'accent' => $setting->value['accent'],
                'soft' => $setting->value['soft'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{group: string, key: string, value: array<string, mixed>}>
     */
    private function settingUpdates(array $payload): array
    {
        $updates = [];

        if (array_key_exists('default_palette', $payload)) {
            $updates[] = ['group' => 'theme', 'key' => 'default_palette', 'value' => ['key' => $payload['default_palette']]];
        }

        if (array_key_exists('invoice_template', $payload)) {
            $updates[] = ['group' => 'theme', 'key' => 'invoice_template', 'value' => ['template' => $payload['invoice_template']]];
        }

        if (array_key_exists('invoice_prefix', $payload)) {
            $updates[] = ['group' => 'invoice', 'key' => 'prefix', 'value' => ['prefix' => $payload['invoice_prefix']]];
        }

        if (array_key_exists('default_tax_rate', $payload)) {
            $updates[] = ['group' => 'invoice', 'key' => 'default_tax_rate', 'value' => ['rate' => (float) $payload['default_tax_rate']]];
        }

        if (array_key_exists('default_due_days', $payload)) {
            $updates[] = ['group' => 'invoice', 'key' => 'default_due_days', 'value' => ['days' => (int) $payload['default_due_days']]];
        }

        if (array_key_exists('reminder_days_before_due', $payload)) {
            $updates[] = ['group' => 'invoice', 'key' => 'reminder_days_before_due', 'value' => ['days' => (int) $payload['reminder_days_before_due']]];
        }

        if (array_key_exists('numbering_reset', $payload)) {
            $updates[] = ['group' => 'invoice', 'key' => 'numbering_reset', 'value' => ['reset' => $payload['numbering_reset']]];
        }

        return $updates;
    }
}
