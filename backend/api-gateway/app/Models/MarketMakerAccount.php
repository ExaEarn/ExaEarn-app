<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
    ];
}
