<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyOrder extends Model
{
    protected $fillable = [
        'copy_order_uuid',
        'copy_relationship_id',
        'lead_trade_event_id',
        'follower_user_id',
        'follower_futures_order_id',
        'follower_spot_order_id',
        'status',
        'priority',
        'worker_token',
        'reason_code',
        'product',
        'symbol',
        'side',
        'lead_execution_price',
        'follower_execution_price',
        'target_quantity',
        'submitted_quantity',
        'executed_quantity',
        'executed_notional',
        'copy_slippage_bps',
        'queued_at',
        'submitted_at',
        'completed_at',
        'risk_snapshot',
        'metadata',
    ];

    protected $casts = [
        'lead_execution_price' => 'decimal:18',
        'follower_execution_price' => 'decimal:18',
        'target_quantity' => 'decimal:18',
        'submitted_quantity' => 'decimal:18',
        'executed_quantity' => 'decimal:18',
        'executed_notional' => 'decimal:18',
        'copy_slippage_bps' => 'decimal:8',
        'queued_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'risk_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CopyRelationship::class, 'copy_relationship_id');
    }

    public function leadTradeEvent(): BelongsTo
    {
        return $this->belongsTo(CopyLeadTradeEvent::class, 'lead_trade_event_id');
    }

    public function followerOrder(): BelongsTo
    {
        return $this->belongsTo(FuturesOrder::class, 'follower_futures_order_id');
    }

    public function followerSpotOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'follower_spot_order_id');
    }
}
