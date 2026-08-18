<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class CompanyLogoUploadApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_company_logo_can_be_uploaded_for_default_profile(): void
    {
        Storage::fake('public');

        $this->post(route('api.company-profile.logo.store'), [
            'logo' => UploadedFile::fake()->image('logo.png', 320, 160),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.business_name', 'Ruang Karya Digital')
            ->assertJsonPath('data.logo_url', fn (?string $url): bool => str_contains($url ?? '', '/storage/company-profiles/logos/'));

        $profile = CompanyProfile::query()->sole();

        $this->assertNotNull($profile->logo_path);
        $this->assertStringStartsWith('company-profiles/logos/', $profile->logo_path);
        Storage::disk('public')->assertExists($profile->logo_path);
    }

    public function test_company_logo_upload_replaces_existing_logo_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-profiles/logos/old-logo.png', 'old logo');

        $profile = CompanyProfile::query()->create([
            'business_name' => 'Yok Printing',
            'email' => 'billing@yokprinting.example',
            'address' => 'Jl. Percetakan No. 10',
            'logo_path' => 'company-profiles/logos/old-logo.png',
        ]);

        $this->post(route('api.company-profile.logo.store'), [
            'logo' => UploadedFile::fake()->image('new-logo.webp', 320, 160),
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $profile->getKey())
            ->assertJsonPath('data.logo_path', fn (?string $path): bool => $path !== 'company-profiles/logos/old-logo.png');

        $profile->refresh();

        Storage::disk('public')->assertMissing('company-profiles/logos/old-logo.png');
        Storage::disk('public')->assertExists($profile->logo_path);
    }

    public function test_company_logo_upload_payload_is_validated(): void
    {
        Storage::fake('public');

        $this->withHeader('Accept', 'application/json')
            ->post(route('api.company-profile.logo.store'), [
                'logo' => UploadedFile::fake()->create('logo.pdf', 32, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }
}
