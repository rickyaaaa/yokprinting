<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:120'],
            'business_scale' => ['sometimes', 'nullable', 'string', 'max:80'],
            'founded_year' => ['sometimes', 'nullable', 'integer', 'between:1800,2100'],
            'pic_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pic_role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'brand_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invoice_template' => ['sometimes', Rule::in([
                CompanyProfile::TEMPLATE_PROFESSIONAL,
                CompanyProfile::TEMPLATE_MODERN,
                CompanyProfile::TEMPLATE_CREATIVE,
            ])],
            'default_tax_rate' => ['sometimes', 'numeric', 'between:0,100'],
            'default_due_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'reminder_days_before_due' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'numbering_reset' => ['sometimes', Rule::in([
                CompanyProfile::NUMBERING_RESET_YEARLY,
                CompanyProfile::NUMBERING_RESET_MONTHLY,
                CompanyProfile::NUMBERING_RESET_NEVER,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
