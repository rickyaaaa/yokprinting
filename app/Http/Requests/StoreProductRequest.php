<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'sku' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:products,sku'],
            'code' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cup_size' => ['sometimes', 'nullable', Rule::in(Product::CUP_SIZES)],
            'cup_model' => ['sometimes', 'nullable', Rule::in(Product::CUP_MODELS)],
            'grammage' => ['sometimes', 'nullable', Rule::in(Product::GRAMMAGES)],
            'screen_printing_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sides' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'unit' => ['sometimes', 'required', Rule::in([Product::UNIT_PCS])],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_order_qty' => ['sometimes', 'integer', 'min:1'],
            'package_conversion' => ['sometimes', 'integer', 'min:1'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_gram' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'dimensions' => ['sometimes', 'nullable', 'array'],
            'dimensions.*' => ['nullable'],
            'moq_quantity' => ['sometimes', 'integer', 'min:1'],
            'order_increment' => ['sometimes', 'integer', 'min:1'],
            'packaging_unit' => ['sometimes', 'string', 'max:20'],
            'track_stock' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
        ];
    }
}
