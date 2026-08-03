<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseProofCleanupTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    /** @var list<string> */
    protected $fillable = [
        'expense_id',
        'disk',
        'path',
        'reason',
        'attempts',
        'last_error',
        'next_attempt_at',
        'status',
        'claim_token',
        'claimed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }
}
