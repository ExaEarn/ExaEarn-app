<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'trade_uuid',
        'market_id',
        'buy_order_id',
        'sell_order_id',
        'pair',
        'price',
        'amount',
        'quote_amount',
        'maker_fee',
        'taker_fee',
        'executed_at',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'amount' => 'decimal:8',
        'quote_amount' => 'decimal:8',
        'maker_fee' => 'decimal:8',
        'taker_fee' => 'decimal:8',
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }
}
