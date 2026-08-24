<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesMarkPriceSnapshot extends Model
{
    protected $fillable = [
        'futures_market_id',
        'symbol',
        'index_price',
        'mark_price',
        'premium_rate',
        'metadata',
        'calculated_at',
    ];

    protected $casts = [
        'index_price' => 'decimal:8',
        'mark_price' => 'decimal:8',
        'premium_rate' => 'decimal:10',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
