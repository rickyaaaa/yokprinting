<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(Expense::categories())],
            'subcategory' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('category') === Expense::CATEGORY_EMPLOYEE),
                Rule::prohibitedIf(fn (): bool => $this->input('category') !== Expense::CATEGORY_EMPLOYEE),
                Rule::in(Expense::employeeSubcategories()),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['required', 'string', 'max:2000'],
            'recipient' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:100'],
            'proof_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
