<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryTransaction extends Model
{
    protected $fillable = [
        'type',
        'asset',
        'amount',
        'timestamp',
        'details',
        'chain',
        'from_address',
        'to_address',
        'tx_hash',
        'currency',
        'reference',
        'status',
        'meta_data',
        'provider',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'timestamp' => 'datetime',
        'details' => 'array',
        'meta_data' => 'array',
    ];
}