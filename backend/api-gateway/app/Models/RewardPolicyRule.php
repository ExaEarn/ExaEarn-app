<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardPolicyRule extends Model
{
    protected $fillable = [
        'rule_uuid',
        'name',
        'product',
        'operation',
        'reward_type',
        'value',
        'percentage_bps',
        'daily_user_cap',
        'lifetime_user_cap',
        'campaign_budget',
        'campaign_spent',
        'reward_asset',
        'country',
        'vip_tier',
        'promotion_code',
        'priority',
        'version',
        'status',
        'effective_from',
        'effective_until',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'approved_at' => 'datetime',
        'conditions' => 'array',
        'metadata' => 'array',
    ];
}
