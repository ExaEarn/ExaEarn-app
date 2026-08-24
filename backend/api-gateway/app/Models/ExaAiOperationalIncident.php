<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiOperationalIncident extends Model
{
    protected $table = 'exaai_operational_incidents';

    protected $fillable = [
        'incident_uuid',
        'severity',
        'status',
        'component',
        'incident_type',
        'portfolio_id',
        'strategy_version_id',
        'market_symbol',
        'expected_state',
        'actual_state',
        'difference',
        'resolution',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'expected_state' => 'array',
        'actual_state' => 'array',
        'difference' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
