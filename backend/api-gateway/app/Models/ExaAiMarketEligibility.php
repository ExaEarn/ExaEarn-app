<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiMarketEligibility extends Model
{
    protected $table = 'exaai_market_eligibilities';

    protected $fillable = [
        'symbol',
        'product',
        'status',
        'risk_tier',
        'min_liquidity',
        'max_exposure',
        'max_concentration_percent',
        'max_slippage_bps',
        'market_data_freshness_seconds',
        'metadata',
    ];

    protected $casts = [
        'min_liquidity' => 'decimal:8',
        'max_exposure' => 'decimal:8',
        'max_concentration_percent' => 'decimal:4',
        'max_slippage_bps' => 'integer',
        'market_data_freshness_seconds' => 'integer',
        'metadata' => 'array',
    ];
}
