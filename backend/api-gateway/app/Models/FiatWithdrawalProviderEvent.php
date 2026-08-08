<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiatWithdrawalProviderEvent extends Model
{
    protected $fillable = [
        'fiat_withdrawal_intent_id',
        'provider',
        'event_id',
        'event_type',
        'status',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(FiatWithdrawalIntent::class, 'fiat_withdrawal_intent_id');
    }
}
