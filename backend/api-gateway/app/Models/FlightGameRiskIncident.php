<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightGameRiskIncident extends Model
{
    protected $fillable = [
        'incident_uuid',
        'type',
        'severity',
        'status',
        'user_id',
        'round_id',
        'bet_id',
        'asset',
        'evidence',
        'resolved_at',
        'resolved_by',
        'resolution',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];
}
