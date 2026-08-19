<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'supplier_price_list_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_snapshot',
        'quantity',
        'unit_price',
        'subtotal',
        'received_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'received_quantity' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Supplier Price List quote (if any) that suggested this line's
     * unit_price - reference only, never re-applied after the PO is saved.
     */
    public function supplierPriceList(): BelongsTo
    {
        return $this->belongsTo(SupplierPriceList::class);
    }
}
