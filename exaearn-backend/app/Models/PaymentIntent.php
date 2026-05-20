<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    protected $fillable = [
        'intent_id',
        'user_id',
        'provider',
        'currency',
        'amount',
        'status',
        'provider_reference',
        'bank_reference',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];
}
