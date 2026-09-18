<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Update replaces the whole draft PO (header + items), so it validates
 * identically to creation.
 */
class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['pay_immediately', 'payment_amount', 'payment_date', 'payment_method', 'payment_reference', 'payment_notes'] as $field) {
            unset($rules[$field]);
        }

        return $rules;
    }
}
