<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingUserRiskProfile extends Model
{
    protected $fillable = ['user_id', 'risk_tier', 'trading_enabled', 'margin_enabled', 'futures_enabled', 'status', 'restrictions'];

    protected $casts = [
        'trading_enabled' => 'boolean',
        'margin_enabled' => 'boolean',
        'futures_enabled' => 'boolean',
        'restrictions' => 'array',
    ];
}
