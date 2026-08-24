<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotShadowComparison extends Model
{
    protected $fillable = [
        'comparison_id',
        'market_id',
        'market_symbol',
        'classification',
        'legacy_result',
        'new_engine_result',
        'differences',
    ];

    protected $casts = [
        'legacy_result' => 'array',
        'new_engine_result' => 'array',
        'differences' => 'array',
    ];
}
