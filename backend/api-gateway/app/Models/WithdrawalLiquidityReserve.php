<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalLiquidityReserve extends Model
{
    protected $guarded = [];

    protected $casts = [
        'minimum_reserve' => 'decimal:18',
        'target_reserve' => 'decimal:18',
        'stress_reserve' => 'decimal:18',
        'pending_withdrawals' => 'decimal:18',
        'available_operational_liquidity' => 'decimal:18',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
