<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_uuid',
        'user_id',
        'market_id',
        'pair',
        'side',
        'type',
        'trigger_order_type',
        'price',
        'stop_price',
        'amount',
        'filled_amount',
        'remaining_amount',
        'locked_amount',
        'locked_currency',
        'status',
        'triggered_at',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'stop_price' => 'decimal:8',
        'amount' => 'decimal:8',
        'filled_amount' => 'decimal:8',
        'remaining_amount' => 'decimal:8',
        'locked_amount' => 'decimal:8',
        'triggered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }
}
