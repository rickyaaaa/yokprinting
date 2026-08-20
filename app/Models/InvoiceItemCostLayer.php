<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemCostLayer extends Model
{
    protected $fillable = [
        'invoice_item_id',
        'inventory_batch_id',
        'qty_consumed',
        'unit_cost',
        'total_cost',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_consumed' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}
