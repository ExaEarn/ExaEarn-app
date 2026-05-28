<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'base_currency',
        'total_value',
        'breakdown',
        'cached_at',
    ];

    protected $casts = [
        'total_value' => 'decimal:18',
        'breakdown' => 'array',
        'cached_at' => 'datetime',
    ];
}
