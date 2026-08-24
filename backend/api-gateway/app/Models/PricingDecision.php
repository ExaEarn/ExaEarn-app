<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingDecision extends Model
{
    protected $fillable = [
        'decision_uuid',
        'user_id',
        'institution_id',
        'pricing_rule_id',
        'rule_version',
        'product',
        'operation',
        'fee_type',
        'gross_amount',
        'fee_amount',
        'discount_amount',
        'rebate_amount',
        'network_fee_amount',
        'provider_fee_amount',
        'net_amount',
        'currency',
        'asset',
        'status',
        'source',
        'context',
        'rule_snapshot',
        'decided_at',
        'expires_at',
    ];

    protected $casts = [
        'context' => 'array',
        'rule_snapshot' => 'array',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class, 'pricing_rule_id');
    }
}
