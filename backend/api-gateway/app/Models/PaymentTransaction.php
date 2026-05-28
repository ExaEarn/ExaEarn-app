<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'payment_intent_id',
        'provider',
        'amount',
        'currency',
        'provider_fee',
        'markup_fee',
        'system_fee',
        'net_amount',
        'status',
        'reference',
        'provider_reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'provider_fee' => 'decimal:8',
        'markup_fee' => 'decimal:8',
        'system_fee' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'metadata' => 'array',
    ];
}
