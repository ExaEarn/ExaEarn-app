<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarginLoadRun extends Model
{
    protected $fillable = [
        'run_id',
        'iterations',
        'operations',
        'failures',
        'duration_ms',
        'status',
        'metrics',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
