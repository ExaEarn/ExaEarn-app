<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuturesOrder extends Model
{
    protected $fillable = [
        'order_uuid',
        'user_id',
        'futures_market_id',
        'symbol',
        'type',
        'side',
        'price',
        'quantity',
        'leverage',
        'notional_value',
        'initial_margin',
        'filled_quantity',
        'remaining_quantity',
        'status',
        'source',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'notional_value' => 'decimal:8',
        'initial_margin' => 'decimal:8',
        'filled_quantity' => 'decimal:8',
        'remaining_quantity' => 'decimal:8',
        'leverage' => 'integer',
        'metadata' => 'array',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(FuturesMarket::class, 'futures_market_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

