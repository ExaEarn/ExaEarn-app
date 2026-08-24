<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotOrderBookSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id',
        'market_id',
        'market_symbol',
        'last_sequence',
        'bids',
        'asks',
        'open_orders',
        'checksum',
    ];

    protected $casts = [
        'bids' => 'array',
        'asks' => 'array',
        'open_orders' => 'array',
    ];
}
