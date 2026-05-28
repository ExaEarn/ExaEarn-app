<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTradingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'skill_level',
        'risk_tolerance',
        'preferred_leverage',
        'max_position_size',
        'daily_loss_limit',
        'account_balance',
        'total_trading_experience_months',
        'preferred_symbols',
        'preferred_strategies',
        'enable_ai_suggestions',
        'enable_auto_trading',
        'ai_trade_mode',
        'auto_trading_max_drawdown',
        'ai_assistant_settings',
    ];

    protected $casts = [
        'preferred_leverage' => 'integer',
        'max_position_size' => 'decimal:8',
        'daily_loss_limit' => 'decimal:8',
        'account_balance' => 'decimal:8',
        'total_trading_experience_months' => 'integer',
        'preferred_symbols' => 'array',
        'preferred_strategies' => 'array',
        'enable_ai_suggestions' => 'boolean',
        'enable_auto_trading' => 'boolean',
        'ai_trade_mode' => 'string',
        'auto_trading_max_drawdown' => 'decimal:2',
        'ai_assistant_settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSkillLevel(): string
    {
        return $this->skill_level ?? 'beginner';
    }

    public function getRiskTolerance(): string
    {
        return $this->risk_tolerance ?? 'low';
    }
}
