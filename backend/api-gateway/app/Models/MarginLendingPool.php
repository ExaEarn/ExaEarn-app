<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarginLendingPool extends Model
{
    protected $fillable = [
        'asset',
        'total_liquidity',
        'available_liquidity',
        'borrowed_liquidity',
        'reserve_balance',
        'status',
        'metadata',
    ];

    protected $casts = [
        'total_liquidity' => 'decimal:18',
        'available_liquidity' => 'decimal:18',
        'borrowed_liquidity' => 'decimal:18',
        'reserve_balance' => 'decimal:18',
        'metadata' => 'array',
    ];
}
