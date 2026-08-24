<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesIndexPriceSnapshot extends Model
{
    protected $fillable = [
        'futures_market_id',
        'symbol',
        'index_price',
        'constituents',
        'status',
        'metadata',
        'calculated_at',
    ];

    protected $casts = [
        'index_price' => 'decimal:8',
        'constituents' => 'array',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
