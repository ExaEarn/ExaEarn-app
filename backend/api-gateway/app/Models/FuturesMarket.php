<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuturesMarket extends Model
{
    protected $fillable = [
        'symbol',
        'min_leverage',
        'max_leverage',
        'maintenance_margin_rate',
        'last_price',
        'status',
    ];

    protected $casts = [
        'min_leverage' => 'integer',
        'max_leverage' => 'integer',
        'maintenance_margin_rate' => 'decimal:8',
        'last_price' => 'decimal:8',
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
