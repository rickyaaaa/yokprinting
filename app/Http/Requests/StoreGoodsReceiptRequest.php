<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
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
            'receipt_date' => ['required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_date.required' => 'Tanggal penerimaan wajib diisi.',
            'items.required' => 'Isi minimal satu jumlah barang yang diterima.',
            'items.min' => 'Isi minimal satu jumlah barang yang diterima.',
            'items.*.purchase_order_item_id.required' => 'Item PO wajib dipilih.',
            'items.*.quantity_received.min' => 'Jumlah diterima tidak boleh negatif.',
        ];
    }
}
