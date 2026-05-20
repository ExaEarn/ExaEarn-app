<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftcardOrder extends Model
{
    protected $fillable = [
        'user_id',
        'giftcard_id',
        'type',
        'amount',
        'currency',
        'status',
        'risk_level',
        'risk_score',
        'payment_method',
        'reference',
        'requires_admin_review',
        'processed_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'requires_admin_review' => 'boolean',
        'processed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function giftcard(): BelongsTo
    {
        return $this->belongsTo(Giftcard::class);
    }

    public function fraudLogs(): HasMany
    {
        return $this->hasMany(FraudLog::class, 'order_id');
    }
}
