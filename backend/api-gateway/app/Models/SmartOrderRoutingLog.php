<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmartOrderRoutingLog extends Model
{
    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'requested_quantity',
        'executed_quantity',
        'avg_execution_price',
        'expected_best_price',
        'slippage_percent',
        'execution_time_ms',
        'route_plan',
        'execution_result',
        'status',
    ];

    protected $casts = [
        'route_plan' => 'array',
        'execution_result' => 'array',
    ];
}
