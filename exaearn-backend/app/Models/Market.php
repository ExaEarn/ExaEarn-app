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
        'last_price',
        'price_precision',
        'min_order_size',
        'max_order_size',
        'maker_fee',
        'taker_fee',
    ];

    protected $casts = [
        'last_price' => 'decimal:8',
        'price_precision' => 'decimal:8',
        'min_order_size' => 'decimal:8',
        'max_order_size' => 'decimal:8',
        'maker_fee' => 'decimal:8',
        'taker_fee' => 'decimal:8',
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
