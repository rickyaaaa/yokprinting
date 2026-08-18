<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const UNIT_PCS = 'Pcs';

    public const CUP_SIZES = ['4 Oz', '8 Oz', '12 Oz', '14 Oz', '16 Oz', '18 Oz', '22 Oz', '16/22 Oz'];

    public const CUP_MODELS = ['Datar', 'Oval'];

    public const GRAMMAGES = ['7gr', '8gr', '9gr', '9.5gr'];

    public const SCREEN_PRINTING_COLORS = ['Hitam', 'Putih', 'Merah', 'Biru', 'Hijau', 'Custom'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const DEFAULT_MINIMUM_STOCK = 500;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => self::UNIT_PCS,
        'purchase_price' => 0,
        'minimum_order_qty' => 500,
        'package_conversion' => 500,
        'minimum_stock' => self::DEFAULT_MINIMUM_STOCK,
        'moq_quantity' => 500,
        'order_increment' => 500,
        'packaging_unit' => 'pcs',
        'track_stock' => false,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * `last_purchase_price` and `average_purchase_cost` are deliberately
     * excluded: they're system-managed reference costs meant to be updated
     * only by purchasing/goods-receipt flows, never by direct user input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'category',
        'brand',
        'cup_size',
        'cup_model',
        'grammage',
        'screen_printing_color',
        'sides',
        'description',
        'short_description',
        'unit',
        'purchase_price',
        'stock',
        'minimum_stock',
        'minimum_order_qty',
        'package_conversion',
        'length_cm',
        'width_cm',
        'height_cm',
        'weight_gram',
        'dimensions',
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
            'purchase_price' => 'decimal:2',
            'last_purchase_price' => 'decimal:2',
            'average_purchase_cost' => 'decimal:2',
            'stock' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'minimum_order_qty' => 'integer',
            'package_conversion' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'weight_gram' => 'decimal:2',
            'dimensions' => 'array',
            'sides' => 'integer',
            'moq_quantity' => 'integer',
            'order_increment' => 'integer',
            'track_stock' => 'boolean',
        ];
    }

    /**
     * Bootstrap product lifecycle hooks.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (! filled($product->sku)) {
                $product->sku = static::nextSku();
            }

            $product->unit = self::UNIT_PCS;
        });

        static::saving(function (Product $product): void {
            $product->unit = self::UNIT_PCS;
        });
    }

    /**
     * Get invoice line items that reference this catalog product.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get stock ledger movements for this product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get suppliers that can provide this product.
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot([
                'purchase_price',
                'minimum_purchase',
                'supplier_unit',
                'is_primary',
            ])
            ->withTimestamps();
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
            ->whereRaw('stock <= COALESCE(minimum_stock, ?)', [self::DEFAULT_MINIMUM_STOCK]);
    }

    /**
     * Resolve the effective minimum stock without treating zero as absent.
     */
    public function minimumStockValue(): float
    {
        return (float) ($this->minimum_stock ?? self::DEFAULT_MINIMUM_STOCK);
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

        return trim(sprintf(
            'Sablon Cup %s - %s%s',
            implode(' ', $parts),
            $this->sides ? "{$this->sides} warna" : '1 warna',
            $ink !== null ? " ({$ink})" : '',
        ));
    }

    /**
     * Alias the existing sku column as product code for client-facing API payloads.
     */
    public function getCodeAttribute(): ?string
    {
        return $this->sku;
    }

    /**
     * Determine if a customer order quantity satisfies MOQ and increment rules.
     */
    public function isValidOrderQuantity(int $quantity): bool
    {
        $increment = max(1, (int) ($this->package_conversion ?: $this->order_increment ?: 500));

        return $quantity > 0 && $quantity % $increment === 0;
    }

    /**
     * Generate the next sequential YokPrinting product code.
     */
    public static function nextSku(): string
    {
        $lastNumber = static::withTrashed()
            ->where('sku', 'like', 'H-%')
            ->pluck('sku')
            ->map(function (?string $sku): int {
                if (! $sku || ! preg_match('/^H-(\d+)$/', $sku, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        return 'H-'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
