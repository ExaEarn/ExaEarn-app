<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPaymentLink extends Model
{
    protected $fillable = [
        'link_id',
        'merchant_id',
        'environment',
        'title',
        'description',
        'amount_mode',
        'amount',
        'currency',
        'maximum_uses',
        'uses_count',
        'status',
        'success_url',
        'cancel_url',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];
}
