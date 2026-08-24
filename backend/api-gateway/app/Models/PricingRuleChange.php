<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRuleChange extends Model
{
    protected $fillable = [
        'change_uuid',
        'pricing_rule_id',
        'action',
        'status',
        'requested_by_admin_id',
        'approved_by_admin_id',
        'previous_value',
        'new_value',
        'impact_preview',
        'reason',
        'approval_reason',
        'approved_at',
    ];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
        'impact_preview' => 'array',
        'approved_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class, 'pricing_rule_id');
    }
}
