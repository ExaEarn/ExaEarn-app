<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalVenueBalance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'available' => 'decimal:18',
        'locked' => 'decimal:18',
        'pending_settlement' => 'decimal:18',
        'reserved_for_routing' => 'decimal:18',
        'operational_minimum' => 'decimal:18',
        'synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ExternalVenueAccount::class, 'external_venue_account_id');
    }
}
