<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopyLeadTradeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'lead_trader_id',
        'lead_user_id',
        'product',
        'symbol',
        'side',
        'position_effect',
        'lead_order_id',
        'lead_trade_id',
        'execution_price',
        'executed_quantity',
        'leverage',
        'margin_mode',
        'sequence',
        'executed_at',
        'metadata',
    ];

    protected $casts = [
        'execution_price' => 'decimal:18',
        'executed_quantity' => 'decimal:18',
        'leverage' => 'integer',
        'sequence' => 'integer',
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function leadTrader(): BelongsTo
    {
        return $this->belongsTo(Trader::class, 'lead_trader_id');
    }

    public function copyOrders(): HasMany
    {
        return $this->hasMany(CopyOrder::class, 'lead_trade_event_id');
    }
}
