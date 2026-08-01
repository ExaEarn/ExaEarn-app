<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaAiOrder extends Model
{
    use HasFactory;

    protected $table = 'exaai_orders';

    protected $fillable = [
        'user_id','session_id','strategy_definition_id','market_type','pair','side','order_type','quantity',
        'entry_price','exit_price','fees','realized_pnl','unrealized_pnl','status','source_order_uuid',
        'source_futures_order_uuid','signal_context','risk_snapshot','opened_at','closed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'fees' => 'decimal:8',
        'realized_pnl' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'signal_context' => 'array',
        'risk_snapshot' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaAiSession::class, 'session_id');
    }
}