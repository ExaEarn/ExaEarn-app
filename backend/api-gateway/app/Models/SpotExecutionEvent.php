<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotExecutionEvent extends Model
{
    protected $fillable = [
        'event_id',
        'market_id',
        'market_symbol',
        'sequence',
        'event_type',
        'order_id',
        'execution_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
