<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const METHOD_TRANSFER_BCA = 'transfer_bca';

    public const METHOD_TRANSFER_MANDIRI = 'transfer_mandiri';

    public const METHOD_TRANSFER_BRI = 'transfer_bri';

    public const METHOD_TRANSFER_BNI = 'transfer_bni';

    public const METHOD_CASH = 'cash';

    public const METHOD_CREDIT_CARD = 'credit_card';

    public const METHOD_OTHER = 'other';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'currency' => 'IDR',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'recorded_by',
        'payment_number',
        'payment_date',
        'method',
        'reference',
        'currency',
        'amount',
        'status',
        'notes',
        'metadata',
        'verified_at',
        'verified_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the invoice this payment belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who recorded the payment.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who verified the payment.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope payments that are still pending verification.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope payments that have been verified.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /**
     * Get the human-readable method label.
     */
    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_TRANSFER_BCA => 'Transfer BCA',
            self::METHOD_TRANSFER_MANDIRI => 'Transfer Mandiri',
            self::METHOD_TRANSFER_BRI => 'Transfer BRI',
            self::METHOD_TRANSFER_BNI => 'Transfer BNI',
            self::METHOD_CASH => 'Tunai',
            self::METHOD_CREDIT_CARD => 'Kartu Kredit',
            default => 'Lainnya',
        };
    }

    /**
     * Get the human-readable status label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_VERIFIED => 'Terverifikasi',
            self::STATUS_REJECTED => 'Ditolak',
            default => $this->status,
        };
    }
}
