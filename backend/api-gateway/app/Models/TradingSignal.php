<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingSignal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'signal_type',
        'confidence',
        'reason',
        'technical_indicators',
        'suggested_entry',
        'suggested_stop_loss',
        'suggested_take_profit',
        'market_condition',
        'volatility_level',
        'trend_strength',
        'risk_reward_ratio',
        'ai_reasoning',
        'is_active',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'technical_indicators' => 'array',
        'suggested_entry' => 'decimal:8',
        'suggested_stop_loss' => 'decimal:8',
        'suggested_take_profit' => 'decimal:8',
        'confidence' => 'integer',
        'risk_reward_ratio' => 'decimal:2',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }
}
