<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_OVERDUE = 'overdue';

    public const PRODUCTION_DRAFT = 'draft';

    public const PRODUCTION_AWAITING_DP = 'awaiting_dp';

    public const PRODUCTION_DESIGN_ACC = 'design_acc';

    public const PRODUCTION_IN_PRODUCTION = 'in_production';

    public const PRODUCTION_READY_FOR_PICKUP = 'ready_for_pickup';

    public const PRODUCTION_COMPLETED = 'completed';

    public const SHIPPING_NONE = 'none';

    public const SHIPPING_PAID_BY_CUSTOMER = 'paid_by_customer';

    public const SHIPPING_COMPANY_FREE_SHIPPING = 'company_free_shipping';

    public const ORDER_PROCESS_DRAFT = 'draft';

    public const ORDER_PROCESS_IN_PRODUCTION = 'in_production';

    public const ORDER_PROCESS_READY_TO_SHIP = 'ready_to_ship';

    public const ORDER_PROCESS_COMPLETED = 'completed';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'payment_status' => self::PAYMENT_UNPAID,
        'production_status' => self::PRODUCTION_DRAFT,
        'currency' => 'IDR',
        'subtotal' => 0,
        'discount_value' => 0,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'shipping_type' => self::SHIPPING_NONE,
        'shipping_cost' => 0,
        'is_free_shipping' => false,
        'order_process_status' => self::ORDER_PROCESS_DRAFT,
        'total_hpp' => 0,
        'gross_profit' => 0,
        'dp_required_percent' => 50,
        'template' => 'default',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by',
        'invoice_number',
        'delivery_note_number',
        'issue_date',
        'due_date',
        'status',
        'payment_status',
        'production_status',
        'currency',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'shipping_type',
        'shipping_cost',
        'is_free_shipping',
        'order_process_status',
        'total_hpp',
        'gross_profit',
        'dp_required_percent',
        'notes',
        'terms',
        'design_notes',
        'mockup_url',
        'template',
        'theme_color',
        'metadata',
        'sent_at',
        'viewed_at',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'is_free_shipping' => 'boolean',
            'total_hpp' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'dp_required_percent' => 'decimal:2',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_follow_up_at' => 'datetime',
        ];
    }

    /**
     * Get the customer billed by the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Get the user who created the invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last recorded a follow-up on this invoice.
     */
    public function lastFollowUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_follow_up_by');
    }

    /**
     * Get the invoice line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the payments recorded against this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope invoices by workflow status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope unpaid invoices whose due date has passed.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->whereNotIn('payment_status', [self::PAYMENT_PAID]);
    }

    /**
     * Scope invoices that have been officially sent to the customer and can be tracked as receivables.
     */
    public function scopeReceivable(Builder $query): Builder
    {
        return $query
            ->finalized()
            ->where('payment_status', '!=', self::PAYMENT_PAID);
    }

    /**
     * Scope invoices that have been issued to the customer and count as business transactions.
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope invoices by production workflow status.
     */
    public function scopeWithProductionStatus(Builder $query, string $status): Builder
    {
        return $query->where('production_status', $status);
    }

    /**
     * Get the human-readable production workflow label.
     */
    public function productionStatusLabel(): string
    {
        return match ($this->production_status) {
            self::PRODUCTION_AWAITING_DP => 'Menunggu DP',
            self::PRODUCTION_DESIGN_ACC => 'ACC Mockup/Desain',
            self::PRODUCTION_IN_PRODUCTION => 'Proses Sablon/Cetak',
            self::PRODUCTION_READY_FOR_PICKUP => 'Siap Diambil/Kirim',
            self::PRODUCTION_COMPLETED => 'Lunas & Selesai',
            default => 'Drafting',
        };
    }

    /**
     * Sum verified payments as received DP/payment.
     */
    public function verifiedPaidAmount(): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->verified()->get();

        return (float) $payments
            ->where('status', Payment::STATUS_VERIFIED)
            ->sum('amount');
    }

    /**
     * Calculate outstanding balance from verified payments.
     */
    public function remainingAmount(): float
    {
        return round(
            max(0, (float) $this->total_amount - $this->verifiedPaidAmount()),
            2,
            PHP_ROUND_HALF_UP,
        );
    }

    /**
     * Calculate the minimum DP required for print production.
     */
    public function requiredDpAmount(): float
    {
        return round((float) $this->total_amount * ((float) $this->dp_required_percent / 100), 2);
    }

    /**
     * Get ordered production workflow steps for UI timelines.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function productionWorkflow(): array
    {
        return [
            ['key' => self::PRODUCTION_DRAFT, 'label' => 'Drafting'],
            ['key' => self::PRODUCTION_AWAITING_DP, 'label' => 'Menunggu DP'],
            ['key' => self::PRODUCTION_DESIGN_ACC, 'label' => 'ACC Mockup/Desain'],
            ['key' => self::PRODUCTION_IN_PRODUCTION, 'label' => 'Proses Sablon/Cetak'],
            ['key' => self::PRODUCTION_READY_FOR_PICKUP, 'label' => 'Siap Diambil/Kirim'],
            ['key' => self::PRODUCTION_COMPLETED, 'label' => 'Lunas & Selesai'],
        ];
    }

    /**
     * Check if a delivery note / surat jalan can be generated for this invoice.
     */
    public function canGenerateDeliveryNote(): bool
    {
        return in_array($this->production_status, [
            self::PRODUCTION_READY_FOR_PICKUP,
            self::PRODUCTION_COMPLETED,
        ], true);
    }

    /**
     * Get or generate a stable delivery note number for this invoice.
     */
    public function deliveryNoteNumber(): string
    {
        if (! empty($this->delivery_note_number)) {
            return $this->delivery_note_number;
        }

        $number = Str::startsWith($this->invoice_number, 'INV-')
            ? Str::replaceFirst('INV-', 'SJ-', $this->invoice_number)
            : 'SJ-'.$this->invoice_number;

        $this->delivery_note_number = $number;
        $this->saveQuietly();

        return $number;
    }
}
