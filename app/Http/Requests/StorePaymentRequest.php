<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'payment_date' => ['required', 'date'],
            'method' => [
                'required',
                'string',
                Rule::in([
                    Payment::METHOD_TRANSFER_BCA,
                    Payment::METHOD_TRANSFER_MANDIRI,
                    Payment::METHOD_TRANSFER_BRI,
                    Payment::METHOD_TRANSFER_BNI,
                    Payment::METHOD_CASH,
                    Payment::METHOD_CREDIT_CARD,
                    Payment::METHOD_OTHER,
                ]),
            ],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    Payment::STATUS_PENDING,
                    Payment::STATUS_VERIFIED,
                ]),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak dikenal.',
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.gt' => 'Nominal pembayaran harus lebih dari Rp0.',
            'status.in' => 'Status pembayaran tidak dikenal.',
        ];
    }
}
