<?php

namespace App\Services\CashBank;

use App\Models\PurchasePayment;

class PurchasePaymentBankMethodPolicy
{
    public function isBankMethod(string $method): bool
    {
        return in_array($method, [
            PurchasePayment::METHOD_BANK_TRANSFER,
            PurchasePayment::METHOD_CREDIT_CARD,
            PurchasePayment::METHOD_QRIS,
        ], true);
    }
}
