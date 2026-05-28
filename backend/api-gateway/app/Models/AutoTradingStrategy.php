<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoTradingStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'symbol',
        'is_active',
        'config',
        'performance_metrics',
        'risk_settings',
        'max_drawdown_percent',
        'daily_loss_limit',
        'trades_executed',
        'total_pnl',
        'win_rate',
        'last_executed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'performance_metrics' => 'array',
        'risk_settings' => 'array',
        'max_drawdown_percent' => 'decimal:2',
        'daily_loss_limit' => 'decimal:8',
        'trades_executed' => 'integer',
        'total_pnl' => 'decimal:8',
        'win_rate' => 'decimal:2',
        'last_executed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutoStrategyExecution::class, 'strategy_id');
    }

    public function isRiskWithinLimits(): bool
    {
        return ($this->daily_loss_limit === null || $this->total_pnl > -$this->daily_loss_limit) &&
               ($this->max_drawdown_percent === null || $this->total_pnl > -(($this->max_drawdown_percent / 100)));
    }
}
