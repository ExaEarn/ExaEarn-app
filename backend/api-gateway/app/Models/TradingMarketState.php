<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingMarketState extends Model
{
    protected $fillable = [
        'market_symbol', 'product', 'state', 'reason_code', 'reason',
        'changed_by_admin_id', 'changed_at', 'metadata',
    ];

    protected $casts = ['metadata' => 'array', 'changed_at' => 'datetime'];
}
