<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Http\Requests\UploadCompanyLogoRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    /**
     * Display the default company profile.
     */
    public function show(): JsonResponse
    {
        $profile = $this->defaultProfile();

        return response()->json([
            'status' => 'success',
            'data' => $this->serializeProfile($profile),
        ]);
    }

    /**
     * Store or update the default company profile.
     */
    public function update(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $profile = CompanyProfile::query()->where('is_default', true)->first();
        $payload = array_merge($request->validated(), ['is_default' => true]);

        if ($profile === null) {
            $profile = CompanyProfile::query()->create($payload);
        } else {
            $profile->update($payload);
            $profile->refresh();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->serializeProfile($profile),
            'message' => 'Company profile saved successfully.',
        ]);
    }

    /**
     * Upload and attach a company logo to the default profile.
     */
    public function uploadLogo(UploadCompanyLogoRequest $request): JsonResponse
    {
        $profile = $this->persistedDefaultProfile();
        $oldLogoPath = $profile->logo_path;
        $logoPath = $request->file('logo')->store('company-profiles/logos', 'public');

        $profile->update(['logo_path' => $logoPath]);
        $profile->refresh();

        if (
            is_string($oldLogoPath)
            && str_starts_with($oldLogoPath, 'company-profiles/logos/')
            && $oldLogoPath !== $logoPath
        ) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->serializeProfile($profile),
            'message' => 'Company logo uploaded successfully.',
        ]);
    }

    /**
     * Resolve an existing default profile or a non-persisted fallback.
     */
    private function defaultProfile(): CompanyProfile
    {
        return CompanyProfile::query()->where('is_default', true)->first()
            ?? new CompanyProfile([
                'business_name' => 'Ruang Karya Digital',
                'legal_name' => 'PT Ruang Karya Digital Indonesia',
                'email' => 'finance@ruangkarya.example',
                'address' => 'Jl. Kemang Timur No. 88',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12730',
                'default_tax_rate' => 11,
            ]);
    }

    /**
     * Resolve or create the profile row that stores mutable company settings.
     */
    private function persistedDefaultProfile(): CompanyProfile
    {
        return CompanyProfile::query()->where('is_default', true)->first()
            ?? CompanyProfile::query()->create([
                'business_name' => 'Ruang Karya Digital',
                'legal_name' => 'PT Ruang Karya Digital Indonesia',
                'email' => 'finance@ruangkarya.example',
                'address' => 'Jl. Kemang Timur No. 88',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12730',
                'default_tax_rate' => 11,
                'is_default' => true,
            ]);
    }

    /**
     * Transform a company profile model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeProfile(CompanyProfile $profile): array
    {
        return [
            'id' => $profile->exists ? $profile->getKey() : null,
            'business_name' => $profile->business_name,
            'legal_name' => $profile->legal_name,
            'business_type' => $profile->business_type,
            'registration_number' => $profile->registration_number,
            'industry' => $profile->industry,
            'business_scale' => $profile->business_scale,
            'founded_year' => $profile->founded_year,
            'pic_name' => $profile->pic_name,
            'pic_role' => $profile->pic_role,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'website' => $profile->website,
            'tax_number' => $profile->tax_number,
            'invoice_prefix' => $profile->invoice_prefix,
            'bank_name' => $profile->bank_name,
            'bank_account' => $profile->bank_account,
            'bank_holder' => $profile->bank_holder,
            'address' => $profile->address,
            'city' => $profile->city,
            'province' => $profile->province,
            'postal_code' => $profile->postal_code,
            'brand_color' => $profile->brand_color,
            'logo_path' => $profile->logo_path,
            'logo_url' => $profile->logo_path ? Storage::disk('public')->url($profile->logo_path) : null,
            'invoice_template' => $profile->invoice_template,
            'default_tax_rate' => (float) $profile->default_tax_rate,
            'default_due_days' => $profile->default_due_days,
            'reminder_days_before_due' => $profile->reminder_days_before_due,
            'numbering_reset' => $profile->numbering_reset,
            'is_default' => $profile->is_default,
            'notes' => $profile->notes,
            'metadata' => $profile->metadata,
            'created_at' => $profile->created_at?->toISOString(),
            'updated_at' => $profile->updated_at?->toISOString(),
        ];
    }
}
