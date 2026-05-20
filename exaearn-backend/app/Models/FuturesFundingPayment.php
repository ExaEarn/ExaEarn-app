<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuturesFundingPayment extends Model
{
    protected $fillable = [
        'user_id',
        'futures_position_id',
        'futures_market_id',
        'symbol',
        'funding_rate',
        'payment_amount',
        'direction',
        'reference',
        'funding_time',
        'metadata',
    ];

    protected $casts = [
        'funding_rate' => 'decimal:10',
        'payment_amount' => 'decimal:8',
        'funding_time' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(FuturesMarket::class, 'futures_market_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(FuturesPosition::class, 'futures_position_id');
    }
}

