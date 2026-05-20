<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardFraudFlag extends Model
{
    protected $table = 'giftcard_fraud_flags';

    protected $fillable = [
        'user_id',
        'flag_type',
        'description',
        'score',
        'requires_review',
        'resolved',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'requires_review' => 'boolean',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('score', '>=', 0.7);
    }
}