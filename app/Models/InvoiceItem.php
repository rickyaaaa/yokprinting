<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit' => 'Pcs',
        'unit_price' => 0,
        'discount_value' => 0,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 0,
        'total_amount' => 0,
        'sort_order' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'sku',
        'cup_size',
        'cup_model',
        'grammage',
        'screen_printing_color',
        'jenis_cetak',
        'moq_quantity',
        'order_increment',
        'packaging_unit',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'purchase_cost_snapshot',
        'hpp_total',
        'unit_hpp',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total_amount',
        'sort_order',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'purchase_cost_snapshot' => 'decimal:2',
            'hpp_total' => 'decimal:2',
            'unit_hpp' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'moq_quantity' => 'integer',
            'order_increment' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Fill item descriptions from cup specs when no custom description is given.
     */
    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item): void {
            if (! $item->description && $item->cup_size) {
                $item->description = $item->cupSpecificationDescription();
            }
        });
    }

    /**
     * Get the parent invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the current catalog product, when it still exists.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costLayers(): HasMany
    {
        return $this->hasMany(InvoiceItemCostLayer::class);
    }

    /**
     * Build a human-readable production item description for sablon cup orders.
     */
    public function cupSpecificationDescription(): string
    {
        $parts = array_filter([
            $this->cup_size,
            $this->cup_model,
            $this->grammage ? "({$this->grammage})" : null,
        ]);

        if ($parts === []) {
            return $this->product_name;
        }

        $printType = $this->jenis_cetak ?: '1 warna';
        $ink = $this->screen_printing_color
            ? "Tinta {$this->screen_printing_color}"
            : null;

        return trim(sprintf(
            'Sablon Cup %s - %s%s',
            implode(' ', $parts),
            $printType,
            $ink !== null ? " ({$ink})" : '',
        ));
    }
}
