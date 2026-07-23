<?php

namespace App\Support;

use App\Models\CompanyProfile;

class ThemeAndDefaultSettings
{
    /**
     * Get the default theme and invoice setting rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return [
            [
                'group' => 'theme',
                'key' => 'palette.sage',
                'value' => [
                    'primary' => '#52772c',
                    'accent' => '#234c8c',
                    'soft' => '#eef5ea',
                ],
                'type' => 'palette',
                'label' => 'Sage profesional',
                'description' => 'Hijau kalem untuk identitas bisnis stabil.',
                'is_public' => true,
                'sort_order' => 10,
            ],
            [
                'group' => 'theme',
                'key' => 'palette.ocean',
                'value' => [
                    'primary' => '#2563eb',
                    'accent' => '#0f766e',
                    'soft' => '#eff6ff',
                ],
                'type' => 'palette',
                'label' => 'Ocean blue',
                'description' => 'Biru bersih untuk layanan modern.',
                'is_public' => true,
                'sort_order' => 20,
            ],
            [
                'group' => 'theme',
                'key' => 'palette.sunset',
                'value' => [
                    'primary' => '#d97706',
                    'accent' => '#be123c',
                    'soft' => '#fff7ed',
                ],
                'type' => 'palette',
                'label' => 'Sunset amber',
                'description' => 'Amber hangat untuk brand kreatif.',
                'is_public' => true,
                'sort_order' => 30,
            ],
            [
                'group' => 'theme',
                'key' => 'palette.ink',
                'value' => [
                    'primary' => '#1f2937',
                    'accent' => '#7c3aed',
                    'soft' => '#f3f4f6',
                ],
                'type' => 'palette',
                'label' => 'Ink premium',
                'description' => 'Gelap elegan untuk invoice korporat.',
                'is_public' => true,
                'sort_order' => 40,
            ],
            [
                'group' => 'theme',
                'key' => 'default_palette',
                'value' => ['key' => 'sage'],
                'type' => 'string',
                'label' => 'Palet tema default',
                'description' => 'Palet warna awal untuk invoice dan pengaturan brand.',
                'is_public' => true,
                'sort_order' => 50,
            ],
            [
                'group' => 'theme',
                'key' => 'invoice_template',
                'value' => ['template' => CompanyProfile::TEMPLATE_PROFESSIONAL],
                'type' => 'string',
                'label' => 'Template invoice default',
                'description' => 'Template awal yang dipakai saat membuat invoice baru.',
                'is_public' => true,
                'sort_order' => 60,
            ],
            [
                'group' => 'invoice',
                'key' => 'prefix',
                'value' => ['prefix' => 'INV'],
                'type' => 'string',
                'label' => 'Prefix nomor invoice',
                'description' => 'Prefix default untuk penomoran invoice otomatis.',
                'is_public' => false,
                'sort_order' => 10,
            ],
            [
                'group' => 'invoice',
                'key' => 'default_tax_rate',
                'value' => ['rate' => 11],
                'type' => 'decimal',
                'label' => 'PPN default',
                'description' => 'Persentase pajak default yang ditawarkan saat membuat invoice.',
                'is_public' => false,
                'sort_order' => 20,
            ],
            [
                'group' => 'invoice',
                'key' => 'default_due_days',
                'value' => ['days' => 14],
                'type' => 'integer',
                'label' => 'Jatuh tempo default',
                'description' => 'Jumlah hari default dari tanggal invoice menuju jatuh tempo.',
                'is_public' => false,
                'sort_order' => 30,
            ],
            [
                'group' => 'invoice',
                'key' => 'reminder_days_before_due',
                'value' => ['days' => 3],
                'type' => 'integer',
                'label' => 'Pengingat sebelum jatuh tempo',
                'description' => 'Jumlah hari sebelum jatuh tempo untuk memunculkan pengingat.',
                'is_public' => false,
                'sort_order' => 40,
            ],
            [
                'group' => 'invoice',
                'key' => 'numbering_reset',
                'value' => ['reset' => CompanyProfile::NUMBERING_RESET_YEARLY],
                'type' => 'string',
                'label' => 'Reset nomor invoice',
                'description' => 'Periode reset nomor invoice otomatis.',
                'is_public' => false,
                'sort_order' => 50,
            ],
        ];
    }
}
