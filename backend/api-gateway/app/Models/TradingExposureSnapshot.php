<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingExposureSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id', 'user_id', 'product', 'asset', 'market_symbol', 'gross_exposure',
        'net_exposure', 'borrowed_amount', 'reserved_amount', 'metadata', 'calculated_at',
    ];

    protected $casts = ['metadata' => 'array', 'calculated_at' => 'datetime'];
}
