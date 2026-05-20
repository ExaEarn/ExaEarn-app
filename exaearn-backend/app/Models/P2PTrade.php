<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P2PTrade extends Model
{
    protected $table = 'p2p_trades';

    protected $fillable = [
        'trade_uuid',
        'ad_id',
        'buyer_id',
        'seller_id',
        'escrow_holder_user_id',
        'asset',
        'fiat_currency',
        'crypto_amount',
        'fiat_amount',
        'price',
        'payment_method',
        'payment_window_minutes',
        'payment_deadline',
        'status',
        'escrow_transaction_id',
        'release_transaction_id',
        'return_transaction_id',
        'payment_sent_at',
        'released_at',
        'cancelled_at',
        'disputed_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'crypto_amount' => 'decimal:8',
        'fiat_amount' => 'decimal:8',
        'price' => 'decimal:8',
        'payment_deadline' => 'datetime',
        'payment_sent_at' => 'datetime',
        'released_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'disputed_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(P2PAd::class, 'ad_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function escrowHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escrow_holder_user_id');
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'escrow_transaction_id');
    }

    public function releaseTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'release_transaction_id');
    }

    public function returnTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'return_transaction_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(P2PMessage::class, 'trade_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(P2PDispute::class, 'trade_id');
    }
}
