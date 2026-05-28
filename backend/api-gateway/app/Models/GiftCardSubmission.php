<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardSubmission extends Model
{
    use HasFactory;

    protected $table = 'giftcard_submissions';

    protected $fillable = [
        'user_id',
        'brand',
        'card_value',
        'currency',
        'card_hash',
        'encrypted_card_code',
        'encrypted_card_pin',
        'status',
        'payout_amount',
        'rate_applied',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'paid_out_at',
        'verification_data',
        'metadata',
    ];

    protected $casts = [
        'card_value' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'rate_applied' => 'decimal:4',
        'verification_data' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'paid_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(GiftCardFraudFlag::class, 'user_id', 'user_id');
    }
}
