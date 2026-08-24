<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalVenueAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trading_enabled' => 'boolean',
        'withdrawals_enabled' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
    ];
}
