<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SupplierPriceList extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * Every price quote from a supplier is a new row - history is immutable
     * by convention (see SaveSupplierPriceList); nothing here is ever
     * silently overwritten except through the explicit correction path.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'product_id',
        'price',
        'valid_from',
        'valid_until',
        'notes',
        'source_reference',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Restrict a query to quotes that are in effect (or will be) on the
     * given date - i.e. not yet expired. Used as a building block for the
     * active-price resolver.
     */
    public function scopeNotExpiredOn(Builder $query, Carbon $date): Builder
    {
        return $query->where(function (Builder $query) use ($date): void {
            $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date->toDateString());
        });
    }

    /**
     * Restrict a query to quotes that are actually in effect on the given
     * date: valid_from <= date AND (valid_until IS NULL OR valid_until >= date).
     */
    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->notExpiredOn($date);
    }

    /**
     * Determine this record's lifecycle status relative to a date (defaults
     * to today, in the application's configured timezone).
     */
    public function status(?Carbon $onDate = null): string
    {
        $today = ($onDate ?? Carbon::today())->toDateString();
        $validFrom = $this->valid_from?->toDateString();
        $validUntil = $this->valid_until?->toDateString();

        if ($validFrom !== null && $validFrom > $today) {
            return self::STATUS_UPCOMING;
        }

        if ($validUntil !== null && $validUntil < $today) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_UPCOMING => 'Akan Berlaku',
            self::STATUS_EXPIRED => 'Expired',
        ];
    }

    public function statusLabel(?Carbon $onDate = null): string
    {
        return self::statusLabels()[$this->status($onDate)] ?? $this->status($onDate);
    }

    /**
     * Whether this quote has already been referenced by a purchase order
     * item - once used it becomes part of that PO's audit trail and should
     * no longer be freely edited (see SaveSupplierPriceList::update).
     */
    public function isUsedInPurchaseOrder(): bool
    {
        return PurchaseOrderItem::query()->where('supplier_price_list_id', $this->getKey())->exists();
    }
}
