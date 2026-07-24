<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cup_size' => ['sometimes', 'nullable', Rule::in(Product::CUP_SIZES)],
            'cup_model' => ['sometimes', 'nullable', Rule::in(Product::CUP_MODELS)],
            'grammage' => ['sometimes', 'nullable', Rule::in(Product::GRAMMAGES)],
            'screen_printing_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sides' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'moq_quantity' => ['sometimes', 'integer', 'min:1'],
            'order_increment' => ['sometimes', 'integer', 'min:1'],
            'packaging_unit' => ['sometimes', 'string', 'max:20'],
            'track_stock' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
        ];
    }
}
