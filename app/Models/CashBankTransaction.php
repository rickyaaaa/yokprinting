<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBankTransaction extends Model
{
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    protected $fillable = [
        'bank_account_id', 'transaction_number', 'transaction_date', 'type', 'category',
        'payment_method', 'amount', 'description', 'source_type', 'source_id', 'status', 'created_by',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function isManual(): bool
    {
        return $this->source_type === self::SOURCE_MANUAL;
    }
}
