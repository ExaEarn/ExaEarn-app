<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = [
        'currency',
        'rate_to_usdt',
        'source',
        'last_updated',
    ];

    protected $casts = [
        'rate_to_usdt' => 'decimal:18',
        'last_updated' => 'datetime',
    ];
}
