<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityReservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:18',
        'remaining_amount' => 'decimal:18',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];
}
