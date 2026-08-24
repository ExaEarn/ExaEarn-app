<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityWithdrawalAddress extends Model
{
    protected $guarded = [];

    protected $casts = [
        'risk_flags' => 'array',
        'first_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}
