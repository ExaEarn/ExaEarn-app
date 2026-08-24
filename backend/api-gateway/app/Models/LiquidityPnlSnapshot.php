<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityPnlSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'realized_pnl' => 'decimal:18',
        'unrealized_pnl' => 'decimal:18',
        'venue_fees' => 'decimal:18',
        'rebalancing_fees' => 'decimal:18',
        'sor_savings' => 'decimal:18',
        'convert_spread_revenue' => 'decimal:18',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
