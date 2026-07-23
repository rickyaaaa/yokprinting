<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeDefaultSettingsRequest extends FormRequest
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
            'default_palette' => ['sometimes', Rule::in(['sage', 'ocean', 'sunset', 'ink'])],
            'invoice_template' => ['sometimes', Rule::in([
                CompanyProfile::TEMPLATE_PROFESSIONAL,
                CompanyProfile::TEMPLATE_MODERN,
                CompanyProfile::TEMPLATE_CREATIVE,
            ])],
            'invoice_prefix' => ['sometimes', 'string', 'max:20'],
            'default_tax_rate' => ['sometimes', 'numeric', 'between:0,100'],
            'default_due_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'reminder_days_before_due' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'numbering_reset' => ['sometimes', Rule::in([
                CompanyProfile::NUMBERING_RESET_YEARLY,
                CompanyProfile::NUMBERING_RESET_MONTHLY,
                CompanyProfile::NUMBERING_RESET_NEVER,
            ])],
        ];
    }
}
