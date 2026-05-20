<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderBook extends Model
{
    protected $fillable = [
        'market_id',
        'pair',
        'bid_orders',
        'ask_orders',
        'last_synced_at',
    ];

    protected $casts = [
        'bid_orders' => 'array',
        'ask_orders' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
