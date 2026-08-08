<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiatWithdrawalIntent extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'beneficiary_id',
        'withdrawal_id',
        'reference',
        'idempotency_key',
        'source_account',
        'country',
        'currency',
        'amount',
        'fee',
        'recipient_receives',
        'remaining_balance_after',
        'provider',
        'bank_code',
        'bank_name',
        'account_number_last4',
        'account_name',
        'narration',
        'estimated_arrival',
        'status',
        'provider_reference',
        'reserve_ledger_reference',
        'settlement_ledger_reference',
        'reversal_ledger_reference',
        'quote_expires_at',
        'submitted_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'fee' => 'decimal:18',
        'recipient_receives' => 'decimal:18',
        'remaining_balance_after' => 'decimal:18',
        'quote_expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'bank_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(FiatWithdrawalBeneficiary::class, 'beneficiary_id');
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function verificationChallenges(): HasMany
    {
        return $this->hasMany(FiatWithdrawalVerificationChallenge::class);
    }
}
