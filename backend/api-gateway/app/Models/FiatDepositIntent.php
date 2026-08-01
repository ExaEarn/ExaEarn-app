<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiatDepositIntent extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'method_id',
        'currency',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'rate_bps',
        'fixed_fee',
        'route_destination',
        'status',
        'instructions',
        'disclosures',
        'metadata',
        'expires_at',
        'paid_at',
        'settled_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:18',
        'fee_amount' => 'decimal:18',
        'net_amount' => 'decimal:18',
        'instructions' => 'array',
        'disclosures' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'settled_at' => 'datetime',
    ];
}
