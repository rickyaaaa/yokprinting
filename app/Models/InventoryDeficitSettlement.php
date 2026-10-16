<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDeficitSettlement extends Model
{
    protected $fillable = [
        'inventory_batch_id',
        'invoice_item_cost_layer_id',
        'goods_receipt_item_id',
        'compensation_batch_id',
        'quantity',
        'unit_cost',
        'source_type',
        'source_reference',
        'source_date',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'source_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function deficitBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function costLayer(): BelongsTo
    {
        return $this->belongsTo(InvoiceItemCostLayer::class, 'invoice_item_cost_layer_id');
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function compensationBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'compensation_batch_id');
    }
}
