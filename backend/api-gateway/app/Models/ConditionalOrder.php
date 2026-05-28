<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionalOrder extends Model
{
    protected $table = 'futures_conditional_orders';

    protected $fillable = [
        'conditional_uuid',
        'user_id',
        'futures_position_id',
        'futures_market_id',
        'symbol',
        'type',
        'trigger_order_type',
        'trigger_price',
        'execution_price',
        'quantity',
        'status',
        'source',
        'metadata',
    ];

    protected $casts = [
        'trigger_price' => 'decimal:8',
        'execution_price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(FuturesPosition::class, 'futures_position_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(FuturesMarket::class, 'futures_market_id');
    }

    public function isTriggered(): bool
    {
        return $this->status === 'triggered';
    }

    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
