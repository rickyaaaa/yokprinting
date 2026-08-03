<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceProductionStatusRequest extends FormRequest
{
    /**
     * Authorization is enforced by the authenticated route and invoice.update permission.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_status' => [
                'required',
                'string',
                Rule::in(array_column(Invoice::productionWorkflow(), 'key')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'production_status.required' => 'Pilih status produksi.',
            'production_status.in' => 'Status produksi tidak dikenal.',
        ];
    }
}
