<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerPerformanceSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'maker_volume' => 'decimal:18',
        'taker_volume' => 'decimal:18',
        'spread_compliance' => 'decimal:8',
        'depth_compliance' => 'decimal:8',
        'quote_presence' => 'decimal:8',
        'reject_rate' => 'decimal:8',
        'rebates' => 'decimal:18',
        'fees' => 'decimal:18',
        'metadata' => 'array',
        'measured_at' => 'datetime',
    ];
}
