<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiPlan extends Model
{
    use HasFactory;

    protected $table = 'exaai_plans';

    protected $fillable = [
        'code','name','billing_interval','settlement_asset','price','annual_price','capital_limit',
        'max_open_positions','analytics_level','execution_tier','affiliate_eligible','is_active',
        'feature_flags','strategy_access','description',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'annual_price' => 'decimal:8',
        'capital_limit' => 'decimal:8',
        'affiliate_eligible' => 'boolean',
        'is_active' => 'boolean',
        'feature_flags' => 'array',
        'strategy_access' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ExaAiSubscription::class, 'plan_id');
    }
}