<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityRiskDecision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reason_codes' => 'array',
        'signals' => 'array',
        'expires_at' => 'datetime',
    ];
}
