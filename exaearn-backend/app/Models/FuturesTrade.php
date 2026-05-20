<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuturesTrade extends Model
{
    protected $fillable = [
        'futures_market_id',
        'buy_order_id',
        'sell_order_id',
        'symbol',
        'price',
        'quantity',
        'notional_value',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'notional_value' => 'decimal:8',
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(FuturesMarket::class, 'futures_market_id');
    }
}

