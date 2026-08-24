<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiPortfolio extends Model
{
    protected $table = 'exaai_portfolios';

    protected $fillable = [
        'user_id',
        'session_id',
        'allocation_id',
        'strategy_definition_id',
        'strategy_version_id',
        'asset',
        'mode',
        'status',
        'allocated_amount',
        'available_amount',
        'reserved_amount',
        'deployed_amount',
        'equity_amount',
        'realized_pnl',
        'unrealized_pnl',
        'high_water_mark',
        'risk_profile',
        'limits',
        'metadata',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:8',
        'available_amount' => 'decimal:8',
        'reserved_amount' => 'decimal:8',
        'deployed_amount' => 'decimal:8',
        'equity_amount' => 'decimal:8',
        'realized_pnl' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'high_water_mark' => 'decimal:8',
        'limits' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaAiSession::class, 'session_id');
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

    public function decisions(): HasMany
    {
        return $this->hasMany(ExaAiDecision::class, 'portfolio_id');
    }
}
