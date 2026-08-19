<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductOptionsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ids' => ['sometimes', 'array', 'max:150'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'status' => ['sometimes', Rule::in([Product::STATUS_ACTIVE])],
            // Dropdown callers (PO form, Harga Supplier form) ask for the
            // whole active catalog at once - keep this above what they request.
            'limit' => ['sometimes', 'integer', 'between:1,500'],
        ];
    }
}
