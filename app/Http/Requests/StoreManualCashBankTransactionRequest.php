<?php

namespace App\Http\Requests;

use App\Models\CashBankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualCashBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'type' => ['required', Rule::in([CashBankTransaction::TYPE_INCOME, CashBankTransaction::TYPE_EXPENSE])],
            'category' => ['required', 'string', 'max:100'],
            'payment_method' => ['sometimes', 'required', Rule::in([
                CashBankTransaction::PAYMENT_METHOD_CASH,
                CashBankTransaction::PAYMENT_METHOD_TRANSFER,
            ])],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
