<?php

namespace App\Http\Requests;

use App\Services\Invoices\CalculateInvoiceTotals;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateInvoicePreviewPdfRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may make this request.
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
            'invoice_number' => ['required', 'string', 'max:50'],
            'issue_date_label' => ['nullable', 'string', 'max:80'],
            'currency' => ['nullable', 'string', 'max:10'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.email' => ['nullable', 'string', 'max:160'],
            'customer.phone' => ['nullable', 'string', 'max:80'],
            'customer.address' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:220'],
            'items.*.note' => ['nullable', 'string', 'max:300'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.line_total' => ['exclude'],
            'discount_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['exclude'],
            'tax_enabled' => ['nullable', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'tax_amount' => ['exclude'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'is_free_shipping' => ['nullable', 'boolean'],
            'dp_required_percent' => ['nullable', 'numeric', 'between:0,100'],
            'dp_amount' => ['exclude'],
            'subtotal' => ['exclude'],
            'grand_total' => ['exclude'],
            'total_amount' => ['exclude'],
            'remaining_amount' => ['exclude'],
            'remaining_payment' => ['exclude'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validate limits that depend on the authoritative item subtotal.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $discountType = $this->input('discount_type', 'percentage');
                $discountValue = $this->input('discount_value', 0);

                if (! is_numeric($discountValue)) {
                    return;
                }

                if ($discountType === 'percentage' && (float) $discountValue > 100) {
                    $validator->errors()->add('discount_value', 'Diskon persentase maksimal 100%.');

                    return;
                }

                if ($discountType !== 'fixed') {
                    return;
                }

                $items = collect($this->input('items', []))
                    ->values()
                    ->map(function (mixed $item, int $index): ?array {
                        if (! is_array($item)) {
                            return null;
                        }

                        $quantity = $item['quantity'] ?? null;
                        $unitPrice = $item['unit_price'] ?? null;

                        if (! is_numeric($quantity) || ! is_numeric($unitPrice)) {
                            return null;
                        }

                        return [
                            'product_id' => $index + 1,
                            'quantity' => $quantity,
                            'price' => $unitPrice,
                        ];
                    });

                if ($items->contains(null) || $items->isEmpty()) {
                    return;
                }

                $totals = app(CalculateInvoiceTotals::class)->calculate(
                    $items->all(),
                    ['type' => 'fixed', 'value' => 0],
                    ['enabled' => false, 'rate' => 0],
                );
                $normalizedDiscountValue = round(max(0, (float) $discountValue), 2, PHP_ROUND_HALF_UP);

                if ($normalizedDiscountValue > $totals['subtotal']) {
                    $validator->errors()->add('discount_value', 'Diskon nominal tidak boleh melebihi subtotal.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.quantity.numeric' => 'Quantity harus berupa angka.',
            'items.*.quantity.gt' => 'Quantity harus lebih dari 0.',
            'items.*.unit_price.numeric' => 'Harga harus berupa angka.',
            'items.*.unit_price.min' => 'Harga tidak boleh negatif.',
            'discount_value.numeric' => 'Nilai diskon harus berupa angka.',
            'discount_value.min' => 'Nilai diskon tidak boleh negatif.',
            'tax_rate.numeric' => 'Tarif pajak harus berupa angka.',
            'tax_rate.between' => 'Tarif pajak harus berada di antara 0-100%.',
            'dp_required_percent.numeric' => 'Nilai DP harus berupa angka.',
            'dp_required_percent.between' => 'DP tidak boleh melebihi grand total.',
        ];
    }
}
