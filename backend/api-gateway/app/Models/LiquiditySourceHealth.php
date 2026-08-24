<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquiditySourceHealth extends Model
{
    protected $table = 'liquidity_source_health';

    protected $guarded = [];

    protected $casts = [
        'rejection_rate_bps' => 'decimal:8',
        'metadata' => 'array',
        'checked_at' => 'datetime',
    ];
}
