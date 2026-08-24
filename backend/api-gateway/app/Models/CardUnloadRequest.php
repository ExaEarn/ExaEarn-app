<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardUnloadRequest extends Model
{
    protected $fillable = [
        'unload_uuid',
        'user_id',
        'card_id',
        'asset',
        'amount',
        'fee_amount',
        'net_amount',
        'status',
        'provider_reference',
        'ledger_reference',
        'idempotency_key',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'fee_amount' => 'decimal:18',
        'net_amount' => 'decimal:18',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
