<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('customers', 'code')->ignore($customer)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'segment' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('customers', 'email')->ignore($customer)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'required', 'string', 'max:2000'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in([Customer::STATUS_ACTIVE, Customer::STATUS_INACTIVE])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
