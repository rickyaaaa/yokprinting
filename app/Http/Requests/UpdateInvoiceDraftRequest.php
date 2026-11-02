<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Update replaces the whole draft invoice (header + items), so it validates
 * identically to creation - see StorePurchaseOrderRequest/UpdatePurchaseOrderRequest
 * for the same pattern - plus the rules for manually changing the invoice
 * number, which only apply when the submitted number differs from the stored
 * one. A legacy stored number is accepted unchanged.
 */
class UpdateInvoiceDraftRequest extends StoreInvoiceDraftRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['invoice_number'] = ['sometimes', 'required', 'string', 'max:50'];

        if ($this->changesInvoiceNumber()) {
            $rules['invoice_number'][] = 'regex:'.Invoice::NUMBER_PATTERN;
            $rules['invoice_number_change_reason'] = ['required', 'string', 'min:5', 'max:500'];

            if ($this->invoice()->renumberNeedsConfirmation()) {
                $rules['confirm_invoice_number_change'] = ['accepted'];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->changesInvoiceNumber() || $validator->errors()->has('invoice_number')) {
                    return;
                }

                if (Invoice::numberIsTaken((string) $this->input('invoice_number'), $this->invoice()->getKey())) {
                    $validator->errors()->add('invoice_number', Invoice::NUMBER_TAKEN_MESSAGE);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'invoice_number.required' => 'Nomor invoice wajib diisi.',
            'invoice_number.max' => 'Nomor invoice maksimal 50 karakter.',
            'invoice_number.regex' => 'Format nomor invoice harus INV-YYYY-MM-NNNN, contoh INV-2026-10-0205.',
            'invoice_number_change_reason.required' => 'Alasan perubahan nomor invoice wajib diisi.',
            'invoice_number_change_reason.min' => 'Alasan perubahan nomor invoice minimal 5 karakter.',
            'invoice_number_change_reason.max' => 'Alasan perubahan nomor invoice maksimal 500 karakter.',
            'confirm_invoice_number_change.accepted' => 'Konfirmasi perubahan nomor untuk invoice yang sudah dikirim atau dibayar.',
        ];
    }

    public function changesInvoiceNumber(): bool
    {
        return $this->filled('invoice_number')
            && (string) $this->input('invoice_number') !== $this->invoice()->invoice_number;
    }

    private function invoice(): Invoice
    {
        return $this->route('invoice');
    }
}
