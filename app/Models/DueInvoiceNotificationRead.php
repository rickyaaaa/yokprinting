<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DueInvoiceNotificationRead extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
