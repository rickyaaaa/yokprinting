<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberHistory extends Model
{
    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'replaced_by',
        'changed_by',
        'reason',
    ];

    /**
     * Get the invoice that used to carry this number.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
