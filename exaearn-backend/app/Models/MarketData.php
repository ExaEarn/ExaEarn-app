<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketData extends Model
{
    protected $table = 'market_data';

    protected $fillable = ['symbol', 'price', 'volume', 'spread', 'volatility', 'timestamp'];

    protected $casts = ['timestamp' => 'datetime'];
}
