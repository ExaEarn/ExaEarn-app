<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyLoadRun extends Model
{
    protected $fillable = [
        'run_id',
        'scenario',
        'followers',
        'successful_decisions',
        'skipped_decisions',
        'failed_decisions',
        'duplicate_decisions',
        'orders_submitted',
        'financial_invariant_failures',
        'duration_ms',
        'p50_decision_ms',
        'p95_decision_ms',
        'p99_decision_ms',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
