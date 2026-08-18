<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPurchaseOrdersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'nullable', Rule::in(array_keys(PurchaseOrder::statusLabels()))],
            'supplier_id' => ['sometimes', 'nullable', 'integer'],
            'per_page' => ['sometimes', 'nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
