<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopySurveillanceEvent extends Model
{
    protected $fillable = [
        'surveillance_event_id',
        'lead_trader_id',
        'copy_relationship_id',
        'event_type',
        'severity',
        'signals',
        'metadata',
    ];

    protected $casts = [
        'signals' => 'array',
        'metadata' => 'array',
    ];
}
