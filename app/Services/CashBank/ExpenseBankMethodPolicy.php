<?php

namespace App\Services\CashBank;

use Illuminate\Support\Str;

class ExpenseBankMethodPolicy
{
    /** @var list<string> */
    private const BANK_METHODS = [
        'transfer bank',
        'bank transfer',
        'transfer bca',
        'transfer mandiri',
        'transfer bri',
        'transfer bni',
        'kartu kredit',
        'credit card',
        'payment gateway',
    ];

    public function isBankMethod(string $method): bool
    {
        return in_array(Str::of($method)->trim()->lower()->squish()->toString(), self::BANK_METHODS, true);
    }
}
