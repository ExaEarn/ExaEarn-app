<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiatWithdrawalVerificationChallenge extends Model
{
    protected $fillable = [
        'fiat_withdrawal_intent_id',
        'user_id',
        'method',
        'code_hash',
        'status',
        'attempts',
        'expires_at',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(FiatWithdrawalIntent::class, 'fiat_withdrawal_intent_id');
    }
}
