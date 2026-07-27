<?php

namespace App\Http\Requests;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', Rule::in([
                StockMovement::TYPE_OPENING_BALANCE,
                StockMovement::TYPE_PURCHASE,
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_STOCK_OPNAME,
                StockMovement::TYPE_RETURN,
            ])],
            'quantity' => ['required', 'numeric'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
