<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentHistoryRequest extends FormRequest
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
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'all',
                    Payment::STATUS_PENDING,
                    Payment::STATUS_VERIFIED,
                    Payment::STATUS_REJECTED,
                ]),
            ],
            'method' => [
                'sometimes',
                'string',
                Rule::in([
                    'all',
                    Payment::METHOD_TRANSFER_BCA,
                    Payment::METHOD_TRANSFER_MANDIRI,
                    Payment::METHOD_TRANSFER_BRI,
                    Payment::METHOD_TRANSFER_BNI,
                    Payment::METHOD_CASH,
                    Payment::METHOD_CREDIT_CARD,
                    Payment::METHOD_OTHER,
                ]),
            ],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'string', Rule::in(['payment_date', 'amount', 'customer', 'invoice_number'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
