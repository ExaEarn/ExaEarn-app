<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerSurveillanceCase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
