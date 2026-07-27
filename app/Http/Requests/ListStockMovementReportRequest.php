<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStockMovementReportRequest extends FormRequest
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
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
