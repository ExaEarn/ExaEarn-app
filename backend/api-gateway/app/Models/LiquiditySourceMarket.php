<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquiditySourceMarket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_notional' => 'decimal:18',
        'min_quantity' => 'decimal:18',
        'fees' => 'array',
        'metadata' => 'array',
    ];
}
