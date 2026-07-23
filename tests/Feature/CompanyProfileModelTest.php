<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_profiles_table_contains_business_brand_and_default_setting_fields(): void
    {
        foreach ([
            'business_name',
            'legal_name',
            'business_type',
            'registration_number',
            'industry',
            'business_scale',
            'founded_year',
            'pic_name',
            'pic_role',
            'email',
            'phone',
            'website',
            'tax_number',
            'invoice_prefix',
            'bank_name',
            'bank_account',
            'bank_holder',
            'address',
            'city',
            'province',
            'postal_code',
            'brand_color',
            'logo_path',
            'invoice_template',
            'default_tax_rate',
            'default_due_days',
            'reminder_days_before_due',
            'numbering_reset',
            'is_default',
            'notes',
            'metadata',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('company_profiles', $column),
                "Expected company_profiles table to contain [{$column}] column.",
            );
        }
    }

    public function test_company_profile_can_be_created_with_defaults_and_casts(): void
    {
        $profile = CompanyProfile::query()->create([
            'business_name' => 'Ruang Karya Digital',
            'legal_name' => 'PT Ruang Karya Digital Indonesia',
            'email' => 'finance@ruangkarya.example',
            'default_tax_rate' => 11,
            'metadata' => ['theme' => 'sage'],
        ]);

        $this->assertSame('INV', $profile->invoice_prefix);
        $this->assertSame(CompanyProfile::TEMPLATE_PROFESSIONAL, $profile->invoice_template);
        $this->assertSame(14, $profile->default_due_days);
        $this->assertTrue($profile->is_default);
        $this->assertSame(['theme' => 'sage'], $profile->metadata);
        $this->assertSame('11.00', $profile->default_tax_rate);
    }
}
