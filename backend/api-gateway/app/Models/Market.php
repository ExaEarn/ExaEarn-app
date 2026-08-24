<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    protected $fillable = [
        'symbol',
        'base_currency',
        'quote_currency',
        'status',
        'trading_status',
        'engine_mode',
        'liquidity_mode',
        'price_authority_mode',
        'external_routing_enabled',
        'external_routing_policy',
        'cutover_state',
        'health_status',
        'engine_mode_updated_at',
        'last_price',
        'price_precision',
        'tick_size',
        'quantity_step',
        'min_order_size',
        'max_order_size',
        'min_notional',
        'max_notional',
        'maker_fee',
        'taker_fee',
    ];

    protected $casts = [
        'last_price' => 'decimal:8',
        'price_precision' => 'decimal:8',
        'tick_size' => 'decimal:18',
        'quantity_step' => 'decimal:18',
        'min_order_size' => 'decimal:8',
        'max_order_size' => 'decimal:8',
        'min_notional' => 'decimal:18',
        'max_notional' => 'decimal:18',
        'maker_fee' => 'decimal:8',
        'taker_fee' => 'decimal:8',
        'engine_mode_updated_at' => 'datetime',
        'external_routing_enabled' => 'boolean',
        'external_routing_policy' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }
}
