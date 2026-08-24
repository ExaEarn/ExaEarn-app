<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerRebatePeriod extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'eligible_maker_volume' => 'decimal:18',
        'disqualified_volume' => 'decimal:18',
        'rebate_amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
