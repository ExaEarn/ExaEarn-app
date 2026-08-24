<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEmergencyControl extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];
}
