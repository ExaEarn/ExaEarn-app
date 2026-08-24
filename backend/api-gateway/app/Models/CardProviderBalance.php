<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardProviderBalance extends Model
{
    protected $fillable = [
        'balance_uuid',
        'provider',
        'currency',
        'available',
        'required_minimum',
        'target',
        'maximum',
        'pending_settlements',
        'pending_refunds',
        'status',
        'metadata',
        'checked_at',
    ];

    protected $casts = [
        'available' => 'decimal:18',
        'required_minimum' => 'decimal:18',
        'target' => 'decimal:18',
        'maximum' => 'decimal:18',
        'pending_settlements' => 'decimal:18',
        'pending_refunds' => 'decimal:18',
        'metadata' => 'array',
        'checked_at' => 'datetime',
    ];
}
