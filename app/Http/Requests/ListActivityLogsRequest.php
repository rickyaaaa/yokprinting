<?php

namespace App\Http\Requests;

use App\Models\ActivityLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListActivityLogsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'module' => ['sometimes', 'nullable', 'string', 'max:80'],
            'action' => ['sometimes', 'nullable', 'string', 'max:80'],
            'risk_level' => ['sometimes', Rule::in([
                'all',
                ActivityLog::RISK_LOW,
                ActivityLog::RISK_MEDIUM,
                ActivityLog::RISK_HIGH,
            ])],
            'actor_role' => ['sometimes', 'nullable', 'string', 'max:80'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'sort' => ['sometimes', Rule::in(['occurred_at', 'created_at', 'module', 'action', 'risk_level'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
