<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P2PAd extends Model
{
    protected $table = 'p2p_ads';

    protected $fillable = [
        'ad_uuid',
        'user_id',
        'type',
        'asset',
        'fiat_currency',
        'price',
        'min_limit',
        'max_limit',
        'available_amount',
        'payment_methods',
        'region',
        'payment_time_limit_minutes',
        'terms_of_trade',
        'requires_kyc',
        'status',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'min_limit' => 'decimal:8',
        'max_limit' => 'decimal:8',
        'available_amount' => 'decimal:8',
        'payment_methods' => 'array',
        'requires_kyc' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(P2PTrade::class, 'ad_id');
    }
}
