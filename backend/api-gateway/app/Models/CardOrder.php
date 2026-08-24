<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardOrder extends Model
{
    protected $fillable = [
        'order_uuid',
        'user_id',
        'card_id',
        'provider_order_id',
        'shipping_address',
        'shipping_fee',
        'production_status',
        'tracking_reference',
        'carrier',
        'shipped_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'shipping_fee' => 'decimal:18',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
