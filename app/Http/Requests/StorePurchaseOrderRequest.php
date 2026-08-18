<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'order_date' => ['required', 'date'],
            'expected_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['sometimes', 'numeric', 'min:0'],
            'other_cost' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
            'order_date.required' => 'Tanggal PO wajib diisi.',
            'expected_date.after_or_equal' => 'Tanggal perkiraan tiba tidak boleh sebelum tanggal PO.',
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.min' => 'Tambahkan minimal satu barang.',
            'items.*.product_id.required' => 'Barang pada setiap baris wajib dipilih.',
            'items.*.product_id.exists' => 'Barang tidak ditemukan.',
            'items.*.quantity.gt' => 'Jumlah barang harus lebih dari 0.',
            'items.*.unit_price.min' => 'Harga tidak boleh negatif.',
        ];
    }
}
