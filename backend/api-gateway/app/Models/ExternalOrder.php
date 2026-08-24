<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:18',
        'price' => 'decimal:18',
        'filled_quantity' => 'decimal:18',
        'average_fill_price' => 'decimal:18',
        'fee' => 'decimal:18',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}
