<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarginOrder extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'margin_order_uuid',
        'user_id',
        'margin_account_id',
        'spot_order_id',
        'client_order_id',
        'pair',
        'side',
        'type',
        'borrow_mode',
        'auto_borrow_asset',
        'auto_borrow_amount',
        'auto_borrow_reference',
        'auto_repay_asset',
        'auto_repay_amount',
        'amount',
        'price',
        'status',
        'risk_snapshot',
        'metadata',
        'submitted_at',
        'cancelled_at',
    ];

    protected $casts = [
        'auto_borrow_amount' => 'decimal:18',
        'auto_repay_amount' => 'decimal:18',
        'amount' => 'decimal:18',
        'price' => 'decimal:18',
        'risk_snapshot' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function marginAccount(): BelongsTo
    {
        return $this->belongsTo(MarginAccount::class);
    }

    public function spotOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'spot_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
