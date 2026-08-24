<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaAiDecision extends Model
{
    protected $table = 'exaai_decisions';

    protected $fillable = [
        'decision_uuid',
        'user_id',
        'session_id',
        'portfolio_id',
        'strategy_definition_id',
        'strategy_version_id',
        'idempotency_key',
        'product',
        'symbol',
        'side',
        'order_type',
        'requested_notional',
        'approved_notional',
        'reference_price',
        'quantity',
        'confidence',
        'risk_decision',
        'status',
        'reason_code',
        'signal_payload',
        'market_snapshot',
        'risk_snapshot',
        'execution_plan',
        'execution_result',
        'sequence',
        'decided_at',
        'expires_at',
        'executed_at',
    ];

    protected $casts = [
        'requested_notional' => 'decimal:8',
        'approved_notional' => 'decimal:8',
        'reference_price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'confidence' => 'integer',
        'signal_payload' => 'array',
        'market_snapshot' => 'array',
        'risk_snapshot' => 'array',
        'execution_plan' => 'array',
        'execution_result' => 'array',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaAiSession::class, 'session_id');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(ExaAiPortfolio::class, 'portfolio_id');
    }
}
