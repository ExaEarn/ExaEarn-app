<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityRouteExecution extends Model
{
    protected $guarded = [];

    protected $casts = [
        'planned_quantity' => 'decimal:18',
        'planned_price' => 'decimal:18',
        'filled_quantity' => 'decimal:18',
        'average_fill_price' => 'decimal:18',
        'fee' => 'decimal:18',
        'metadata' => 'array',
    ];
}
