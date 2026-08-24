<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreOperationalAlert extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'metadata' => 'array',
        'last_triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}

