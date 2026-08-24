<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesAdlEvent extends Model
{
    protected $fillable = [
        'adl_id',
        'symbol',
        'futures_position_id',
        'user_id',
        'rank_score',
        'quantity',
        'status',
        'metadata',
    ];

    protected $casts = [
        'rank_score' => 'decimal:8',
        'quantity' => 'decimal:8',
        'metadata' => 'array',
    ];
}
