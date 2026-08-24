<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerIncident extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'opened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
