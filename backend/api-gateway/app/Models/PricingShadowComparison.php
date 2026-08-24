<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingShadowComparison extends Model
{
    protected $fillable = [
        'comparison_uuid',
        'product',
        'operation',
        'legacy_fee_amount',
        'engine_fee_amount',
        'difference_amount',
        'status',
        'context',
    ];

    protected $casts = ['context' => 'array'];
}
