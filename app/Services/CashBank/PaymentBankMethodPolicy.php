<?php

namespace App\Services\CashBank;

use App\Models\Payment;

class PaymentBankMethodPolicy
{
    public function isBankMethod(string $method): bool
    {
        return in_array($method, [
            Payment::METHOD_TRANSFER_BCA,
            Payment::METHOD_TRANSFER_MANDIRI,
            Payment::METHOD_TRANSFER_BRI,
            Payment::METHOD_TRANSFER_BNI,
            Payment::METHOD_CREDIT_CARD,
        ], true);
    }
}
