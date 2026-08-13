<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'opening_balance' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'max:9999999999999.99', 'min:-9999999999999.99'],
        ];
    }
}
