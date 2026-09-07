<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SummariseCashBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Kas & Bank summary cards accept the same date range as the
     * transaction history, so the headline figures describe the rows shown
     * underneath them. Both ends are optional; with neither set the summary
     * falls back to the current calendar month.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
