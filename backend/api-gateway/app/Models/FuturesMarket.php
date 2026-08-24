<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuturesMarket extends Model
{
    protected $fillable = [
        'symbol',
        'base_asset',
        'quote_asset',
        'settlement_asset',
        'contract_type',
        'min_leverage',
        'max_leverage',
        'maintenance_margin_rate',
        'last_price',
        'tick_size',
        'quantity_step',
        'min_quantity',
        'max_quantity',
        'min_notional',
        'max_notional',
        'index_price',
        'mark_price',
        'funding_rate',
        'next_funding_time',
        'status',
        'engine_mode',
        'risk_tiers',
        'price_band_bps',
        'metadata',
    ];

    protected $casts = [
        'min_leverage' => 'integer',
        'max_leverage' => 'integer',
        'maintenance_margin_rate' => 'decimal:8',
        'last_price' => 'decimal:8',
        'tick_size' => 'decimal:8',
        'quantity_step' => 'decimal:8',
        'min_quantity' => 'decimal:8',
        'max_quantity' => 'decimal:8',
        'min_notional' => 'decimal:8',
        'max_notional' => 'decimal:8',
        'index_price' => 'decimal:8',
        'mark_price' => 'decimal:8',
        'funding_rate' => 'decimal:10',
        'next_funding_time' => 'datetime',
        'risk_tiers' => 'array',
        'metadata' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(FuturesOrder::class, 'futures_market_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(FuturesPosition::class, 'futures_market_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(FuturesTrade::class, 'futures_market_id');
    }

    public function fundingPayments(): HasMany
    {
        return $this->hasMany(FuturesFundingPayment::class, 'futures_market_id');
    }
}
