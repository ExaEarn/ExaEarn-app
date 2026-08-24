<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollateralConfiguration extends Model
{
    protected $fillable = [
        'asset', 'collateral_factor', 'max_collateral_amount', 'concentration_threshold_bps',
        'volatility_category', 'status', 'version', 'effective_at',
    ];

    protected $casts = ['effective_at' => 'datetime'];
}
