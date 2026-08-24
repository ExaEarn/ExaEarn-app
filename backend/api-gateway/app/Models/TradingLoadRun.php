<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingLoadRun extends Model
{
    protected $fillable = [
        'run_id', 'scope', 'iterations', 'operations', 'failures', 'p50_ms',
        'p95_ms', 'p99_ms', 'duration_ms', 'status', 'metrics', 'started_at', 'finished_at',
    ];

    protected $casts = ['metrics' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
}
