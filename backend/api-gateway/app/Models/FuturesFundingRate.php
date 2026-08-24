<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesFundingRate extends Model
{
    protected $fillable = [
        'futures_market_id',
        'symbol',
        'index_price',
        'mark_price',
        'funding_rate',
        'funding_time',
        'metadata',
    ];

    protected $casts = [
        'index_price' => 'decimal:8',
        'mark_price' => 'decimal:8',
        'funding_rate' => 'decimal:10',
        'funding_time' => 'datetime',
        'metadata' => 'array',
    ];
}
