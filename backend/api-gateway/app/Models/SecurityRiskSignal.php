<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityRiskSignal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'detected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
