<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotExternalVenueAccount extends Model
{
    protected $fillable = [
        'venue',
        'asset',
        'available_balance',
        'locked_balance',
        'status',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'available_balance' => 'decimal:18',
        'locked_balance' => 'decimal:18',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];
}
