<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateProductStockRequest extends FormRequest
{
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
            'items' => ['required', 'array', 'min:1', 'max:150'],
            'items.*' => ['required', 'array:id,field,value,expected_value,expected_updated_at'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.field' => ['required', 'string', Rule::in(['stock', 'minimum_stock'])],
            'items.*.value' => ['required', 'numeric', 'min:0'],
            'items.*.expected_value' => ['required', 'numeric', 'min:0'],
            'items.*.expected_updated_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.id.distinct' => 'ID produk duplikat tidak diperbolehkan.',
            'items.*.field.in' => 'Field hanya boleh stock atau minimum_stock.',
            'items.*.value.numeric' => 'Nilai stok harus berupa angka.',
            'items.*.value.min' => 'Nilai stok tidak boleh negatif.',
            'items.*.expected_value.required' => 'Nilai awal produk wajib dikirim untuk mencegah konflik perubahan.',
            'items.*.expected_updated_at.required' => 'Versi produk wajib dikirim untuk mencegah konflik perubahan.',
            'items.*.expected_updated_at.date' => 'Versi produk tidak valid.',
        ];
    }
}
