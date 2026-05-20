<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Giftcard extends Model
{
    protected $hidden = [
        'encrypted_code',
        'card_hash',
    ];

    protected $fillable = [
        'owner_user_id',
        'order_id',
        'card_type',
        'provider',
        'amount',
        'currency',
        'encrypted_code',
        'card_hash',
        'status',
        'risk_level',
        'verified_source',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'verified_source' => 'boolean',
        'metadata' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GiftcardOrder::class, 'order_id');
    }
}
