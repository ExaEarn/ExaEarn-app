<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'rule_uuid',
        'name',
        'product',
        'operation',
        'fee_type',
        'value',
        'fixed_value',
        'percentage_bps',
        'spread_bps',
        'min_fee',
        'max_fee',
        'currency',
        'asset',
        'network',
        'market_symbol',
        'country',
        'vip_tier',
        'merchant_tier',
        'user_id',
        'institution_id',
        'promotion_code',
        'precedence_scope',
        'priority',
        'version',
        'status',
        'allow_negative',
        'requires_maker_checker',
        'effective_from',
        'effective_until',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'allow_negative' => 'boolean',
        'requires_maker_checker' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'approved_at' => 'datetime',
        'conditions' => 'array',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
