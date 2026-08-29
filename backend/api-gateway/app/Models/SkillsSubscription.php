<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsSubscription extends Model
{
    protected $fillable = ['user_id', 'plan_code', 'status', 'billing_cycle', 'amount', 'settlement_asset', 'renewal_reference', 'starts_at', 'ends_at', 'cancels_at', 'cancelled_at', 'metadata', 'pricing_snapshot', 'idempotency_key'];

    protected $casts = [
        'amount' => 'decimal:8',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancels_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'pricing_snapshot' => 'array',
    ];
}
