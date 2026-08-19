<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPriceListRequest extends FormRequest
{
    /**
     * Authorization is enforced by the authenticated route and permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'price' => ['required', 'numeric', 'gt:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
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
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier tidak ditemukan.',
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'price.required' => 'Harga wajib diisi.',
            'price.gt' => 'Harga harus lebih dari 0.',
            'valid_from.required' => 'Tanggal mulai berlaku wajib diisi.',
            'valid_until.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai berlaku.',
        ];
    }
}
