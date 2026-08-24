<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketMakerMarketAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'minimum_depth' => 'decimal:18',
        'maximum_spread_bps' => 'decimal:8',
        'minimum_quote_presence' => 'decimal:8',
        'target_quote_size' => 'decimal:18',
        'maximum_inventory' => 'decimal:18',
        'rebate_profile' => 'array',
        'obligations' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MarketMakerProfile::class, 'market_maker_id');
    }
}
