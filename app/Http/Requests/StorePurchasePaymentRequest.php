<?php

namespace App\Http\Requests;

use App\Models\PurchasePayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'string', Rule::in(array_keys(PurchasePayment::methodOptions()))],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak dikenal.',
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.gt' => 'Nominal pembayaran harus lebih dari Rp0.',
        ];
    }
}
