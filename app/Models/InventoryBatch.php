<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = [
        'product_id',
        'goods_receipt_item_id',
        'purchase_date',
        'qty_received',
        'qty_remaining',
        'unit_cost',
        'source_type',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'qty_received' => 'decimal:4',
            'qty_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function costLayers(): HasMany
    {
        return $this->hasMany(InvoiceItemCostLayer::class);
    }
}
