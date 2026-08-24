<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingRiskLimit extends Model
{
    protected $fillable = [
        'limit_id', 'scope', 'scope_key', 'product', 'asset', 'market_symbol',
        'max_order_notional', 'max_position_notional', 'max_borrow_amount',
        'max_leverage', 'max_concentration_bps', 'status', 'version', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];
}
