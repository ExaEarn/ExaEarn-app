<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotMarketEngineLease extends Model
{
    protected $fillable = [
        'market_id',
        'market_symbol',
        'owner_instance_id',
        'lease_token',
        'generation',
        'acquired_at',
        'heartbeat_at',
        'expires_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'acquired_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];
}
