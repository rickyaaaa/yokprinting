<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerSalesProfitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')],
            'status' => ['sometimes', 'string', Rule::in([
                'all',
                Invoice::PAYMENT_PAID,
                Invoice::PAYMENT_PARTIAL,
                Invoice::PAYMENT_UNPAID,
                Invoice::PAYMENT_OVERDUE,
            ])],
        ];
    }
}
