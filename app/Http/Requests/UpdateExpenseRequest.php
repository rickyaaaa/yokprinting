<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExpenseRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'category' => ['sometimes', 'required', Rule::in(Expense::categories())],
            'subcategory' => ['sometimes', 'nullable', Rule::in(Expense::employeeSubcategories())],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['sometimes', 'required', 'string', 'max:2000'],
            'recipient' => ['sometimes', 'required', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'required', 'string', 'max:100'],
            'proof_payment' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * Validate the category and subcategory combination after merging partial input.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Expense|null $expense */
            $expense = $this->route('expense');
            $category = $this->input('category', $expense?->category);
            $subcategory = $this->exists('subcategory')
                ? $this->input('subcategory')
                : $expense?->subcategory;

            if ($category === Expense::CATEGORY_EMPLOYEE && blank($subcategory)) {
                $validator->errors()->add('subcategory', 'Subkategori wajib dipilih untuk Biaya Karyawan.');
            }

            if ($category !== Expense::CATEGORY_EMPLOYEE && filled($subcategory)) {
                $validator->errors()->add('subcategory', 'Subkategori hanya tersedia untuk Biaya Karyawan.');
            }
        }];
    }
}
