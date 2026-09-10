<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A payment made against a purchase order.
 *
 * Only a verified payment moves money: that is the state
 * CashBankService::recordPurchasePayment acts on, and cancelling one reverses
 * the Kas & Bank transaction rather than deleting it, so the ledger keeps its
 * history.
 *
 * A purchase payment is deliberately not an Expense. Expenses are operational
 * costs and feed the profit and loss report; buying stock is not one, and
 * recording it there would count the same money twice - once as a purchase and
 * again as an operating cost.
 */
class PurchasePayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CREDIT_CARD = 'credit_card';

    public const METHOD_QRIS = 'qris';

    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'purchase_order_id',
        'payment_number',
        'payment_date',
        'method',
        'reference',
        'amount',
        'status',
        'notes',
        'recorded_by',
        'verified_at',
        'verified_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cashBankTransaction(): HasOne
    {
        return $this->hasOne(CashBankTransaction::class, 'source_id')
            ->where('source_type', CashBankTransaction::SOURCE_PURCHASE_PAYMENT);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public static function methodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Tunai',
            self::METHOD_BANK_TRANSFER => 'Transfer Bank',
            self::METHOD_CREDIT_CARD => 'Kartu Kredit',
            self::METHOD_QRIS => 'QRIS',
            self::METHOD_OTHER => 'Lainnya',
        ];
    }

    public function methodLabel(): string
    {
        return self::methodOptions()[$this->method] ?? $this->method;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_VERIFIED => 'Terverifikasi',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => 'Menunggu',
        };
    }
}
