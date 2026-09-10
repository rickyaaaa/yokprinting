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

    /**
     * Money paid out against a purchase order.
     *
     * Its own source rather than SOURCE_EXPENSE, because buying stock is not an
     * operating cost. The profit and loss report reads operating costs from the
     * Expense table and, beyond that, only picks up SOURCE_MANUAL rows - so a
     * purchase payment stays out of it by construction. Filed under
     * SOURCE_EXPENSE the same money would have been counted twice: once as
     * stock bought, again as an operating cost.
     */
    public const SOURCE_PURCHASE_PAYMENT = 'purchase_payment';

    public const SOURCE_MANUAL = 'manual';

    /** Kas & Bank category for a purchase payment. Deliberately not an Expense category. */
    public const CATEGORY_PURCHASE_PAYMENT = 'purchase_payment';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    /**
     * The Pengeluaran category a manual outflow counts as, or null when it is
     * not a business cost at all.
     *
     * Owner withdrawals are equity drawings and balance corrections are
     * bookkeeping fixes - counting either as a cost would understate profit.
     * Tax is left out until its classification is settled, since remitting
     * VAT collected from customers is not the company's expense.
     *
     * Shared by the profit & loss report and
     * AppConsoleCommandsAuditManualCashBankExpenses so the report and
     * the audit can never disagree about what counts.
     */
    public static function expenseCategoryFor(string $category): ?string
    {
        if (in_array($category, ['owner_withdrawal', 'balance_adjustment', 'tax'], true)) {
            return null;
        }

        $mapped = match ($category) {
            'operational_cost' => Expense::CATEGORY_OPERATIONAL,
            'bank_fee' => Expense::CATEGORY_BANK_FEE,
            default => $category,
        };

        return in_array($mapped, Expense::categories(), true) ? $mapped : null;
    }

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
