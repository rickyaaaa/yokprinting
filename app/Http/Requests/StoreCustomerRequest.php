<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:customers,code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:customers,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in([
                Customer::STATUS_ACTIVE,
                Customer::STATUS_INACTIVE,
                Customer::STATUS_INACTIVE_1M,
                Customer::STATUS_AUTO_FOLLOWUP,
            ])],
            'last_order_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
