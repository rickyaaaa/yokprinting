<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_profile_endpoint_returns_fallback_when_profile_is_empty(): void
    {
        $this->getJson(route('api.company-profile.show'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', null)
            ->assertJsonPath('data.business_name', 'Ruang Karya Digital')
            ->assertJsonPath('data.invoice_prefix', 'INV')
            ->assertJsonPath('data.default_due_days', 14);
    }

    public function test_company_profile_can_be_saved_and_loaded(): void
    {
        $payload = [
            'business_name' => 'Ruang Karya Digital Studio',
            'legal_name' => 'PT Ruang Karya Digital Indonesia',
            'business_type' => 'Perseroan Terbatas',
            'registration_number' => 'NIB-9120310045517',
            'industry' => 'Jasa kreatif & percetakan digital',
            'business_scale' => 'Perusahaan menengah',
            'founded_year' => 2018,
            'pic_name' => 'Andi Pratama',
            'pic_role' => 'Direktur Operasional',
            'email' => 'finance@ruangkarya.example',
            'phone' => '+62 21 5088 7721',
            'website' => 'https://ruangkarya.example',
            'tax_number' => '09.876.543.2-101.000',
            'invoice_prefix' => 'INV',
            'bank_name' => 'Bank Central Asia',
            'bank_account' => '7721998877',
            'bank_holder' => 'PT Ruang Karya Digital Indonesia',
            'address' => 'Jl. Kemang Timur No. 88',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12730',
            'brand_color' => '#2563eb',
            'invoice_template' => CompanyProfile::TEMPLATE_MODERN,
            'default_tax_rate' => 11,
            'default_due_days' => 21,
            'reminder_days_before_due' => 5,
            'numbering_reset' => CompanyProfile::NUMBERING_RESET_MONTHLY,
            'notes' => 'Pembayaran maksimal sesuai jatuh tempo.',
            'metadata' => ['palette' => 'ocean'],
        ];

        $this->putJson(route('api.company-profile.update'), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.business_name', 'Ruang Karya Digital Studio')
            ->assertJsonPath('data.brand_color', '#2563eb')
            ->assertJsonPath('data.invoice_template', CompanyProfile::TEMPLATE_MODERN)
            ->assertJsonPath('data.default_due_days', 21)
            ->assertJsonPath('data.metadata.palette', 'ocean');

        $this->assertDatabaseCount('company_profiles', 1);

        $this->getJson(route('api.company-profile.show'))
            ->assertOk()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.email', 'finance@ruangkarya.example')
            ->assertJsonPath('data.numbering_reset', CompanyProfile::NUMBERING_RESET_MONTHLY);
    }

    public function test_company_profile_save_payload_is_validated(): void
    {
        $this->putJson(route('api.company-profile.update'), [
            'business_name' => '',
            'email' => 'not-an-email',
            'website' => 'not-a-url',
            'invoice_prefix' => '',
            'address' => '',
            'brand_color' => 'blue',
            'invoice_template' => 'classic',
            'default_tax_rate' => 150,
            'default_due_days' => -1,
            'numbering_reset' => 'daily',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_name',
                'email',
                'website',
                'invoice_prefix',
                'address',
                'brand_color',
                'invoice_template',
                'default_tax_rate',
                'default_due_days',
                'numbering_reset',
            ]);
    }
}
