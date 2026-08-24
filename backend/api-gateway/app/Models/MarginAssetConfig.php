<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarginAssetConfig extends Model
{
    protected $fillable = [
        'asset',
        'borrow_enabled',
        'collateral_enabled',
        'collateral_factor',
        'liquidation_factor',
        'borrow_limit',
        'minimum_borrow',
        'maximum_borrow',
        'reserve_factor',
        'interest_model',
        'base_rate',
        'slope_1',
        'optimal_utilization',
        'slope_2',
        'max_rate',
        'status',
        'metadata',
    ];

    protected $casts = [
        'borrow_enabled' => 'boolean',
        'collateral_enabled' => 'boolean',
        'collateral_factor' => 'decimal:8',
        'liquidation_factor' => 'decimal:8',
        'borrow_limit' => 'decimal:18',
        'minimum_borrow' => 'decimal:18',
        'maximum_borrow' => 'decimal:18',
        'reserve_factor' => 'decimal:8',
        'base_rate' => 'decimal:8',
        'slope_1' => 'decimal:8',
        'optimal_utilization' => 'decimal:8',
        'slope_2' => 'decimal:8',
        'max_rate' => 'decimal:8',
        'metadata' => 'array',
    ];
}
