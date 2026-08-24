<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardDispute extends Model
{
    protected $fillable = [
        'dispute_uuid',
        'card_id',
        'user_id',
        'card_transaction_id',
        'provider_dispute_id',
        'status',
        'amount',
        'currency',
        'evidence',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
