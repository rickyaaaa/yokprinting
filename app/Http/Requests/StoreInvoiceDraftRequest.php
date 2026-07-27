<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'items.*.cup_size' => ['sometimes', 'nullable', Rule::in(Product::CUP_SIZES)],
            'items.*.cup_model' => ['sometimes', 'nullable', Rule::in(Product::CUP_MODELS)],
            'items.*.grammage' => ['sometimes', 'nullable', Rule::in(Product::GRAMMAGES)],
            'items.*.screen_printing_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.jenis_cetak' => ['sometimes', 'nullable', Rule::in(['1 warna', '2 warna', '3 warna'])],
            'items.*.moq_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.order_increment' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.packaging_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.description' => ['sometimes', 'nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'shipping_type' => ['sometimes', Rule::in([
                Invoice::SHIPPING_NONE,
                Invoice::SHIPPING_PAID_BY_CUSTOMER,
                Invoice::SHIPPING_COMPANY_FREE_SHIPPING,
            ])],
            'shipping_cost' => ['sometimes', 'numeric', 'min:0'],
            'order_process_status' => ['sometimes', Rule::in([
                Invoice::ORDER_PROCESS_DRAFT,
                Invoice::ORDER_PROCESS_IN_PRODUCTION,
                Invoice::ORDER_PROCESS_READY_TO_SHIP,
                Invoice::ORDER_PROCESS_COMPLETED,
            ])],
            'discount' => ['required', 'array'],
            'discount.type' => ['required', 'in:percentage,fixed'],
            'discount.value' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'array'],
            'tax.enabled' => ['required', 'boolean'],
            'tax.rate' => ['required', 'numeric', 'between:0,100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'terms' => ['sometimes', 'nullable', 'string'],
            'production_status' => ['sometimes', Rule::in([
                Invoice::PRODUCTION_DRAFT,
                Invoice::PRODUCTION_AWAITING_DP,
                Invoice::PRODUCTION_DESIGN_ACC,
                Invoice::PRODUCTION_IN_PRODUCTION,
                Invoice::PRODUCTION_READY_FOR_PICKUP,
                Invoice::PRODUCTION_COMPLETED,
            ])],
            'design_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mockup_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'dp_required_percent' => ['sometimes', 'numeric', 'between:0,100'],
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

                $products = Product::query()
                    ->whereIn('id', collect($this->input('items', []))->pluck('product_id')->filter()->unique()->values())
                    ->get()
                    ->keyBy('id');

                foreach ($this->input('items', []) as $index => $item) {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    $product = $products->get($item['product_id'] ?? null);

                    if (! $product instanceof Product) {
                        continue;
                    }

                    $minimum = max(1, (int) $product->minimum_order_qty);
                    $increment = max(1, (int) $product->package_conversion);
                    $unit = strtolower($product->unit ?: Product::UNIT_PCS);

                    if ($minimum > 0 && $quantity < $minimum) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Jumlah item minimal {$minimum} {$unit}.",
                        );

                        continue;
                    }

                    if ($increment > 0 && $quantity % $increment !== 0) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Jumlah item harus kelipatan {$increment} {$unit}.",
                        );
                    }
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
