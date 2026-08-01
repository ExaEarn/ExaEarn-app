<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsChallengeEscrow extends Model
{
    protected $fillable = [
        'challenge_id', 'sponsor_user_id', 'winner_user_id', 'asset', 'amount', 'paid_amount',
        'status', 'funding_reference', 'payout_reference', 'idempotency_key', 'metadata', 'funded_at', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'paid_amount' => 'decimal:8',
        'metadata' => 'array',
        'funded_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(SkillsChallenge::class, 'challenge_id');
    }
}
