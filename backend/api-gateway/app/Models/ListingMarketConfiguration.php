<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingMarketConfiguration extends Model
{
    protected $fillable = ['market_config_uuid', 'application_id', 'market_id', 'symbol', 'base_asset', 'quote_asset', 'tick_size', 'quantity_step', 'min_quantity', 'max_quantity', 'min_notional', 'maker_fee', 'taker_fee', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }
}
