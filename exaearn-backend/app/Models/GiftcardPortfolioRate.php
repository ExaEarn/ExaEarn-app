<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftcardPortfolioRate extends Model
{
    protected $table = 'giftcard_portfolio_rates';

    protected $fillable = [
        'card_type',
        'rate_to_usdt',
        'currency',
        'source',
        'last_updated',
    ];

    protected $casts = [
        'rate_to_usdt' => 'decimal:18',
        'last_updated' => 'datetime',
    ];
}