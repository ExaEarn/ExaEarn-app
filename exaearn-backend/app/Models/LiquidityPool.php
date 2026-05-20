<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityPool extends Model
{
    protected $fillable = ['symbol', 'base_asset_balance', 'quote_asset_balance'];

    protected $casts = [
        'base_asset_balance' => 'decimal:8',
        'quote_asset_balance' => 'decimal:8',
    ];
}
