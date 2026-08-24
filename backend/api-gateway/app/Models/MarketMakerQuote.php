<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerQuote extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:18',
        'quantity' => 'decimal:18',
        'reserved_inventory' => 'decimal:18',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];
}
