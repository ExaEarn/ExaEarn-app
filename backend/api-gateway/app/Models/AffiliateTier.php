<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateTier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'commission_rate_bps',
        'monthly_cap',
        'minimum_payout',
        'payout_frequency',
        'eligible_products',
        'qualification_rules',
        'status',
    ];

    protected $casts = [
        'commission_rate_bps' => 'decimal:8',
        'monthly_cap' => 'decimal:18',
        'minimum_payout' => 'decimal:18',
        'eligible_products' => 'array',
        'qualification_rules' => 'array',
    ];
}
