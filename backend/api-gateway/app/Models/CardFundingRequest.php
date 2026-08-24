<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFundingRequest extends Model
{
    protected $fillable = [
        'funding_uuid',
        'user_id',
        'card_id',
        'card_funding_quote_id',
        'source_asset',
        'card_currency',
        'source_amount',
        'card_amount',
        'fee_amount',
        'provider_fee',
        'provider_cost',
        'total_debit',
        'status',
        'reservation_id',
        'provider_reference',
        'ledger_reference',
        'idempotency_key',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'source_amount' => 'decimal:18',
        'card_amount' => 'decimal:18',
        'fee_amount' => 'decimal:18',
        'provider_fee' => 'decimal:18',
        'provider_cost' => 'decimal:18',
        'total_debit' => 'decimal:18',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CardFundingQuote::class, 'card_funding_quote_id');
    }
}
