<?php

namespace App\Http\Requests;

use App\Support\ProfitLossPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfitLossReportRequest extends FormRequest
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
            'period' => ['sometimes', 'string', Rule::in(ProfitLossPeriod::options())],
            'date_from' => [
                'exclude_unless:period,'.ProfitLossPeriod::CUSTOM,
                'required_if:period,'.ProfitLossPeriod::CUSTOM,
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'exclude_unless:period,'.ProfitLossPeriod::CUSTOM,
                'required_if:period,'.ProfitLossPeriod::CUSTOM,
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ];
    }
}
