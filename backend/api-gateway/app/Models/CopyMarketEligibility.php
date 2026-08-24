<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyMarketEligibility extends Model
{
    protected $fillable = [
        'symbol',
        'spot_copy_public_enabled',
        'futures_copy_public_enabled',
        'minimum_liquidity',
        'maximum_copy_aum',
        'maximum_copy_concentration',
        'maximum_slippage_bps',
        'risk_tier',
        'status',
        'metadata',
    ];

    protected $casts = [
        'spot_copy_public_enabled' => 'boolean',
        'futures_copy_public_enabled' => 'boolean',
        'minimum_liquidity' => 'decimal:18',
        'maximum_copy_aum' => 'decimal:18',
        'maximum_copy_concentration' => 'decimal:8',
        'maximum_slippage_bps' => 'decimal:8',
        'metadata' => 'array',
    ];
}
