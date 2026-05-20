<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceFeed extends Model
{
    protected $fillable = [
        'asset_symbol',
        'price_in_usdt',
        'source',
        'last_updated',
    ];

    protected $casts = [
        'price_in_usdt' => 'decimal:18',
        'last_updated' => 'datetime',
    ];
}
