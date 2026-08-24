<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingPriceSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id', 'market_symbol', 'product', 'price_type', 'price', 'source',
        'source_timestamp', 'constituents', 'rejected_sources', 'status', 'calculation_version',
    ];

    protected $casts = ['constituents' => 'array', 'rejected_sources' => 'array', 'source_timestamp' => 'datetime'];
}
