<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingPriceSourceHealth extends Model
{
    protected $table = 'trading_price_source_health';

    protected $fillable = ['source', 'market_symbol', 'status', 'last_price', 'last_seen_at', 'last_error', 'metadata'];

    protected $casts = ['metadata' => 'array', 'last_seen_at' => 'datetime'];
}
