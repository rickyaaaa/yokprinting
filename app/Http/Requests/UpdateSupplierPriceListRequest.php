<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierPriceListRequest extends FormRequest
{
    /**
     * Authorization is enforced by the authenticated route and permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Correction only - supplier_id/product_id are the record's identity
     * and can't be changed; a different supplier/product needs a new quote.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'numeric', 'gt:0'],
            'valid_from' => ['sometimes', 'date'],
            // Not cross-validated against valid_from here since either side
            // may be omitted on a partial correction; SaveSupplierPriceList
            // checks the final merged date pair before saving instead.
            'valid_until' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'source_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.gt' => 'Harga harus lebih dari 0.',
            'valid_until.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai berlaku.',
        ];
    }
}
