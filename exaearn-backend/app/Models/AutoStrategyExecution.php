<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoStrategyExecution extends Model
{
    use HasFactory;

    protected $table = 'auto_strategy_executions';

    protected $fillable = [
        'strategy_id',
        'user_id',
        'order_uuid',
        'side',
        'quantity',
        'entry_price',
        'exit_price',
        'pnl',
        'status',
        'reason',
        'signal_data',
        'executed_at',
        'closed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'pnl' => 'decimal:8',
        'signal_data' => 'array',
        'executed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(AutoTradingStrategy::class, 'strategy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
