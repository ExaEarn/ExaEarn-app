<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerConfig extends Model
{
    protected $fillable = ['symbol', 'spread_percentage', 'order_size', 'max_orders', 'status', 'meta'];

    protected $casts = ['meta' => 'array'];
}
