<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const CUP_SIZES = ['12 Oz', '14 Oz', '16 Oz', '18 Oz', '22 Oz'];

    public const CUP_MODELS = ['Datar', 'Oval'];

    public const GRAMMAGES = ['7gr', '8gr', '9gr', '9.5gr'];

    public const SCREEN_PRINTING_COLORS = ['Hitam', 'Putih', 'Merah', 'Biru', 'Hijau', 'Custom'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => 'item',
        'price' => 0,
        'moq_quantity' => 1000,
        'order_increment' => 1000,
        'packaging_unit' => 'pcs',
        'track_stock' => false,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'category',
        'cup_size',
        'cup_model',
        'grammage',
        'screen_printing_color',
        'sides',
        'description',
        'unit',
        'price',
        'stock',
        'minimum_stock',
        'moq_quantity',
        'order_increment',
        'packaging_unit',
        'track_stock',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'sides' => 'integer',
            'moq_quantity' => 'integer',
            'order_increment' => 'integer',
            'track_stock' => 'boolean',
        ];
    }

    /**
     * Get invoice line items that reference this catalog product.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the normalized product category.
     */
    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Scope active products selectable on transactional forms.
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope active tracked products whose stock is at or below minimum stock.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('track_stock', true)
            ->whereNotNull('stock')
            ->whereNotNull('minimum_stock')
            ->whereColumn('stock', '<=', 'minimum_stock');
    }

    /**
     * Build the print-ready cup specification used on invoice item snapshots.
     */
    public function cupDescription(): string
    {
        $parts = array_filter([
            $this->cup_size,
            $this->cup_model,
            $this->grammage ? "({$this->grammage})" : null,
        ]);

        if ($parts === []) {
            return $this->name;
        }

        $ink = $this->screen_printing_color
            ? "Tinta {$this->screen_printing_color}"
            : null;
        $sides = $this->sides
            ? "{$this->sides} Sisi"
            : null;
        $detail = implode(' - ', array_filter([$ink, $sides]));

        return trim(sprintf(
            'Sablon Cup %s - 1 Warna%s',
            implode(' ', $parts),
            $detail !== '' ? " ({$detail})" : '',
        ));
    }

    /**
     * Determine if a customer order quantity satisfies MOQ and increment rules.
     */
    public function isValidOrderQuantity(int $quantity): bool
    {
        $minimum = max(1, (int) $this->moq_quantity);
        $increment = max(1, (int) $this->order_increment);

        return $quantity >= $minimum && $quantity % $increment === 0;
    }
}
