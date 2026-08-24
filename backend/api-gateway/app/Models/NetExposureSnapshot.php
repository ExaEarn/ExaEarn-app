<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetExposureSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'user_liability' => 'decimal:18',
        'treasury_assets' => 'decimal:18',
        'external_venue_exposure' => 'decimal:18',
        'reserved_withdrawal_liquidity' => 'decimal:18',
        'net_exposure' => 'decimal:18',
        'coverage_ratio' => 'decimal:18',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
