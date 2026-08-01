<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiSession extends Model
{
    use HasFactory;

    protected $table = 'exaai_sessions';

    protected $fillable = [
        'user_id','subscription_id','allocation_id','strategy_definition_id','strategy_version_id','mode','status',
        'risk_level','duration_label','starts_at','ends_at','paused_at','stopped_at','max_daily_loss',
        'max_drawdown_percent','max_open_positions','eligible_markets','constraints','stop_conditions','metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'stopped_at' => 'datetime',
        'max_daily_loss' => 'decimal:8',
        'max_drawdown_percent' => 'decimal:4',
        'eligible_markets' => 'array',
        'constraints' => 'array',
        'stop_conditions' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ExaAiSubscription::class, 'subscription_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ExaAiCapitalAllocation::class, 'allocation_id');
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(ExaAiStrategyDefinition::class, 'strategy_definition_id');
    }

    public function strategyVersion(): BelongsTo
    {
        return $this->belongsTo(ExaAiStrategyVersion::class, 'strategy_version_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ExaAiOrder::class, 'session_id');
    }
}