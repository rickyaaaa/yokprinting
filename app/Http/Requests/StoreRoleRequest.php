<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'code' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')],
            'guard_name' => ['sometimes', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'scope' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([
                Role::STATUS_ACTIVE,
                Role::STATUS_LIMITED,
                Role::STATUS_DISABLED,
            ])],
            // System roles are provisioned by seeders and must not be
            // created or promoted through the public role-management API.
            'is_system' => ['prohibited'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ];
    }
}
