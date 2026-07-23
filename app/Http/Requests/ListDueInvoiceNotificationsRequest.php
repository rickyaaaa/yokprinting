<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDueInvoiceNotificationsRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', Rule::in(['all', 'overdue', 'due_today', 'due_soon'])],
            'days' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['due_date', 'outstanding', 'customer', 'invoice_number'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
