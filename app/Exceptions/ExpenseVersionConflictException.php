<?php

namespace App\Exceptions;

use RuntimeException;

class ExpenseVersionConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct('Pengeluaran telah diubah oleh pengguna lain. Muat ulang data sebelum mencoba lagi.');
    }
}
