<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerRebalanceRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
