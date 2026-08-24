<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotMarketDataEvent extends Model
{
    protected $fillable = [
        'event_id',
        'market_id',
        'market_symbol',
        'sequence',
        'event_type',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
