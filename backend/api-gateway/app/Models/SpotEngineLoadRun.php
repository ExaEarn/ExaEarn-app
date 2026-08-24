<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotEngineLoadRun extends Model
{
    protected $fillable = [
        'run_id',
        'market_symbol',
        'orders_submitted',
        'orders_accepted',
        'trades_created',
        'duration_ms',
        'p50_latency_ms',
        'p95_latency_ms',
        'p99_latency_ms',
        'error_count',
        'metadata',
    ];

    protected $casts = [
        'duration_ms' => 'decimal:3',
        'p50_latency_ms' => 'decimal:3',
        'p95_latency_ms' => 'decimal:3',
        'p99_latency_ms' => 'decimal:3',
        'metadata' => 'array',
    ];
}
