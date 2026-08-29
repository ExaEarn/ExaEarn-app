<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommissionEvent extends Model
{
    protected $fillable = [
        'event_uuid',
        'referral_id',
        'affiliate_user_id',
        'referred_user_id',
        'reward_policy_decision_id',
        'referral_reward_id',
        'product',
        'event_type',
        'source_reference',
        'gross_revenue',
        'commissionable_base',
        'commission_rate_bps',
        'commission_amount',
        'reward_asset',
        'status',
        'hold_until',
        'qualified_at',
        'available_at',
        'paid_at',
        'policy_snapshot',
        'metadata',
    ];

    protected $casts = [
        'gross_revenue' => 'decimal:18',
        'commissionable_base' => 'decimal:18',
        'commission_rate_bps' => 'decimal:8',
        'commission_amount' => 'decimal:18',
        'hold_until' => 'datetime',
        'qualified_at' => 'datetime',
        'available_at' => 'datetime',
        'paid_at' => 'datetime',
        'policy_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
