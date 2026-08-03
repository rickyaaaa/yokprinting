<?php

namespace App\Exceptions;

use RuntimeException;

class ExpenseProofUnavailableException extends RuntimeException
{
    public function __construct(public readonly int $expenseId)
    {
        parent::__construct('Bukti pengeluaran tidak tersedia sehingga transaksi tidak dapat dipulihkan.');
    }
}
