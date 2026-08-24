<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFundingQuote extends Model
{
    protected $fillable = [
        'quote_uuid',
        'user_id',
        'card_id',
        'source_asset',
        'card_currency',
        'source_amount',
        'card_amount',
        'fx_rate',
        'conversion_fee',
        'card_fee',
        'provider_fee',
        'provider_cost',
        'total_debit',
        'pricing_snapshot',
        'status',
        'expires_at',
        'consumed_at',
        'metadata',
    ];

    protected $casts = [
        'source_amount' => 'decimal:18',
        'card_amount' => 'decimal:18',
        'fx_rate' => 'decimal:18',
        'conversion_fee' => 'decimal:18',
        'card_fee' => 'decimal:18',
        'provider_fee' => 'decimal:18',
        'provider_cost' => 'decimal:18',
        'total_debit' => 'decimal:18',
        'pricing_snapshot' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
