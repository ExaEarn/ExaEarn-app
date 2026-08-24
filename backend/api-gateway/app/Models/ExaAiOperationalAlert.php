<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiOperationalAlert extends Model
{
    protected $table = 'exaai_operational_alerts';

    protected $fillable = [
        'alert_uuid',
        'dedupe_key',
        'severity',
        'status',
        'component',
        'condition',
        'message',
        'context',
        'last_triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'last_triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
