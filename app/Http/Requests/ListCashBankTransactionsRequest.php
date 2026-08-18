<?php

namespace App\Http\Requests;

use App\Models\CashBankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCashBankTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in([CashBankTransaction::TYPE_INCOME, CashBankTransaction::TYPE_EXPENSE])],
            'category' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', Rule::in([
                CashBankTransaction::PAYMENT_METHOD_CASH,
                CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
