<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsSubscription extends Model
{
    protected $fillable = ['user_id', 'plan_code', 'status', 'billing_cycle', 'amount', 'settlement_asset', 'starts_at', 'ends_at', 'metadata'];

    protected $casts = [
        'amount' => 'decimal:8',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];
}
