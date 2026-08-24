<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAuthorization extends Model
{
    protected $fillable = [
        'authorization_uuid',
        'card_id',
        'user_id',
        'provider',
        'provider_authorization_id',
        'amount',
        'currency',
        'merchant',
        'status',
        'ledger_reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'metadata' => 'array',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
