<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportProductCatalogRequest extends FormRequest
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
            // Mirrors the product list page's own filter pills, so exporting
            // gives back exactly what is on screen.
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'active', 'low_stock', 'inactive'])],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
