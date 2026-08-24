<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketLiquidityHealthSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'best_bid' => 'decimal:18',
        'best_ask' => 'decimal:18',
        'spread_bps' => 'decimal:8',
        'bid_depth' => 'decimal:18',
        'ask_depth' => 'decimal:18',
        'quote_presence' => 'decimal:8',
        'score' => 'decimal:8',
        'reasons' => 'array',
        'measured_at' => 'datetime',
    ];
}
