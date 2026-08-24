<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryLiquidityBucket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'allocated_amount' => 'decimal:18',
        'reserved_amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
