<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuturesPosition extends Model
{
    protected $fillable = [
        'user_id',
        'futures_market_id',
        'symbol',
        'side',
        'entry_price',
        'mark_price',
        'quantity',
        'leverage',
        'margin_type',
        'margin',
        'maintenance_margin',
        'unrealized_pnl',
        'realized_pnl',
        'liquidation_price',
        'status',
        'metadata',
    ];

    protected $casts = [
        'entry_price' => 'decimal:8',
        'mark_price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'margin' => 'decimal:8',
        'maintenance_margin' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'realized_pnl' => 'decimal:8',
        'liquidation_price' => 'decimal:8',
        'leverage' => 'integer',
        'margin_type' => 'string',
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

