<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGrossProfitReportRequest extends FormRequest
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
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['sometimes', 'string', Rule::in([
                'all',
                Invoice::PAYMENT_UNPAID,
                Invoice::PAYMENT_PARTIAL,
                Invoice::PAYMENT_PAID,
                Invoice::PAYMENT_OVERDUE,
            ])],
        ];
    }
}
