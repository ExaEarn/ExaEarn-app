<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesLiquidationEvent extends Model
{
    protected $fillable = [
        'liquidation_id',
        'user_id',
        'futures_position_id',
        'futures_market_id',
        'symbol',
        'mark_price',
        'liquidation_price',
        'quantity',
        'liquidation_fee',
        'insurance_impact',
        'status',
        'ledger_reference',
        'metadata',
    ];

    protected $casts = [
        'mark_price' => 'decimal:8',
        'liquidation_price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'liquidation_fee' => 'decimal:8',
        'insurance_impact' => 'decimal:8',
        'metadata' => 'array',
    ];
}
