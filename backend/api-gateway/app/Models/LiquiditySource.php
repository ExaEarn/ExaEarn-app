<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquiditySource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'capabilities' => 'array',
        'configuration' => 'array',
        'metadata' => 'array',
        'last_health_at' => 'datetime',
    ];
}
