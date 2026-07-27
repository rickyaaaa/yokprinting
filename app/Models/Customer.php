<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const ACTIVITY_ACTIVE = 'active';

    public const ACTIVITY_NEEDS_FOLLOW_UP = 'needs_follow_up';

    public const ACTIVITY_NEVER_ORDERED = 'never_ordered';

    /**
     * Accessors appended to array and JSON serialization.
     *
     * @var list<string>
     */
    protected $appends = [
        'activity_status',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'tax_number',
        'status',
        'notes',
    ];

    /**
     * Get the customer's display initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * Get all invoices issued to the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get paid invoices used to calculate customer activity.
     */
    public function paidInvoices(): HasMany
    {
        return $this->invoices()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('payment_status', Invoice::PAYMENT_PAID);
    }

    /**
     * Compute the customer's activity status from the latest paid invoice.
     */
    public function getActivityStatusAttribute(): string
    {
        $latestPaidAt = $this->paidInvoices()
            ->selectRaw('COALESCE(paid_at, issue_date) as latest_paid_at')
            ->orderByRaw('COALESCE(paid_at, issue_date) desc')
            ->value('latest_paid_at');

        if (! $latestPaidAt) {
            return self::ACTIVITY_NEVER_ORDERED;
        }

        return now()->parse($latestPaidAt)->gte(now()->subDays(30))
            ? self::ACTIVITY_ACTIVE
            : self::ACTIVITY_NEEDS_FOLLOW_UP;
    }

    /**
     * Scope customers whose last paid invoice is older than the follow-up threshold.
     */
    public function scopeNeedsFollowUp(Builder $query, int $days = 30): Builder
    {
        $cutoff = now()->subDays($days);

        return $query
            ->whereHas('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('payment_status', Invoice::PAYMENT_PAID))
            ->whereDoesntHave('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('payment_status', Invoice::PAYMENT_PAID)
                ->whereRaw('COALESCE(paid_at, issue_date) >= ?', [$cutoff]));
    }

    /**
     * Scope active customers selectable on transactional forms.
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
