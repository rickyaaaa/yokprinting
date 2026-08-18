<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_FULLY_RECEIVED = 'fully_received';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency' => 'IDR',
        'subtotal' => 0,
        'shipping_cost' => 0,
        'other_cost' => 0,
        'grand_total' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * `status`, `approved_by`/`approved_at`, and `cancelled_*` are
     * deliberately excluded: they only change through the dedicated
     * submit/approve/cancel services, never through direct mass update.
     *
     * @var list<string>
     */
    protected $fillable = [
        'po_number',
        'supplier_id',
        'order_date',
        'expected_date',
        'currency',
        'subtotal',
        'shipping_cost',
        'other_cost',
        'grand_total',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function hasPostedGoodsReceipt(): bool
    {
        return $this->goodsReceipts()->where('status', GoodsReceipt::STATUS_POSTED)->exists();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Line items may only be added/replaced while the PO is still a draft -
     * once submitted, prices and quantities are locked for audit history.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_CLOSED], true)
            && ! $this->hasPostedGoodsReceipt();
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_WAITING_APPROVAL => 'Menunggu Approval',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_PARTIALLY_RECEIVED => 'Sebagian Diterima',
            self::STATUS_FULLY_RECEIVED => 'Sudah Diterima',
            self::STATUS_INVOICED => 'Sudah Ditagih',
            self::STATUS_PARTIALLY_PAID => 'Sebagian Dibayar',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_CLOSED => 'Ditutup',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }
}
