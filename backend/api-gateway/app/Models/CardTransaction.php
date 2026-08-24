<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTransaction extends Model
{
    protected $fillable = [
        'transaction_uuid',
        'card_id',
        'user_id',
        'provider',
        'provider_transaction_id',
        'provider_reference',
        'type',
        'merchant',
        'mcc',
        'country',
        'transaction_currency',
        'transaction_amount',
        'billing_currency',
        'billing_amount',
        'fee',
        'provider_cost',
        'fx_rate',
        'authorization_reference',
        'status',
        'provider_created_at',
        'metadata',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:18',
        'billing_amount' => 'decimal:18',
        'fee' => 'decimal:18',
        'provider_cost' => 'decimal:18',
        'fx_rate' => 'decimal:18',
        'provider_created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
