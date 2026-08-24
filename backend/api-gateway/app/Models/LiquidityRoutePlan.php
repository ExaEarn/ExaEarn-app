<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityRoutePlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_quantity' => 'decimal:18',
        'limit_price' => 'decimal:18',
        'expected_average_price' => 'decimal:18',
        'expected_total_cost' => 'decimal:18',
        'quality_score' => 'decimal:8',
        'sources_considered' => 'array',
        'plan' => 'array',
        'rejections' => 'array',
        'metadata' => 'array',
    ];
}
