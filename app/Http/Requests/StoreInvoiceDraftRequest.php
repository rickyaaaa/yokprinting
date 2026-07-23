<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvoiceDraftRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'min:1'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.description' => ['sometimes', 'nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'array'],
            'discount.type' => ['required', 'in:percentage,fixed'],
            'discount.value' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'array'],
            'tax.enabled' => ['required', 'boolean'],
            'tax.rate' => ['required', 'numeric', 'between:0,100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'terms' => ['sometimes', 'nullable', 'string'],
            'template' => ['sometimes', 'string', 'max:50'],
            'theme_color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('discount.type') === 'percentage'
                    && (float) $this->input('discount.value', 0) > 100
                ) {
                    $validator->errors()->add(
                        'discount.value',
                        'Diskon persentase maksimal 100%.',
                    );
                }
            },
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
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal invoice.',
            'items.required' => 'Tambahkan minimal satu item invoice.',
            'items.min' => 'Tambahkan minimal satu item invoice.',
            'items.*.product_id.required' => 'Produk pada setiap baris wajib dipilih.',
            'items.*.quantity.gt' => 'Jumlah item harus lebih dari 0.',
            'items.*.price.min' => 'Harga item tidak boleh negatif.',
            'tax.rate.between' => 'Tarif pajak harus berada di antara 0–100%.',
        ];
    }
}
