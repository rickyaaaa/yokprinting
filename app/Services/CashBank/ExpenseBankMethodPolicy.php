<?php

namespace App\Services\CashBank;

use App\Models\Expense;

class ExpenseBankMethodPolicy
{
    public function isBankMethod(string $method): bool
    {
        return in_array($method, [
            Expense::METHOD_BANK_TRANSFER,
            Expense::METHOD_CREDIT_CARD,
            Expense::METHOD_QRIS,
        ], true);
    }
}
