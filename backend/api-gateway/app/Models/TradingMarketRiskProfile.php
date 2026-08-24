<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingMarketRiskProfile extends Model
{
    protected $fillable = [
        'market_symbol', 'product', 'risk_tier', 'max_order_notional',
        'max_position_notional', 'max_leverage', 'status', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];
}
