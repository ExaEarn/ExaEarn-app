<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = [
        'card_uuid',
        'user_id',
        'card_customer_id',
        'provider',
        'provider_card_id',
        'card_product',
        'type',
        'currency',
        'network',
        'last_four',
        'expiry_month',
        'expiry_year',
        'status',
        'nickname',
        'physical_status',
        'provider_status',
        'idempotency_key',
        'controls',
        'limits',
        'metadata',
    ];

    protected $casts = [
        'controls' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CardCustomer::class, 'card_customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CardTransaction::class);
    }
}
