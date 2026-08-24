<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardPolicyDecision extends Model
{
    protected $fillable = [
        'decision_uuid',
        'reward_policy_rule_id',
        'user_id',
        'product',
        'operation',
        'gross_amount',
        'reward_amount',
        'reward_asset',
        'status',
        'reason_code',
        'context',
        'rule_snapshot',
        'decided_at',
    ];

    protected $casts = [
        'context' => 'array',
        'rule_snapshot' => 'array',
        'decided_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RewardPolicyRule::class, 'reward_policy_rule_id');
    }
}
