<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PARTIALLY_CONSUMED = 'partially_consumed';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reservation_id',
        'account_id',
        'user_id',
        'asset',
        'amount',
        'remaining_amount',
        'purpose',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'status',
        'metadata',
        'expires_at',
        'consumed_at',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'remaining_amount' => 'decimal:18',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
