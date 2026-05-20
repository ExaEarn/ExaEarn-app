<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryBalance extends Model
{
    protected $fillable = [
        'asset',
        'balance',
        'hot_wallet_balance',
        'cold_wallet_balance',
    ];

    protected $casts = [
        'balance' => 'decimal:18',
        'hot_wallet_balance' => 'decimal:18',
        'cold_wallet_balance' => 'decimal:18',
    ];
}
