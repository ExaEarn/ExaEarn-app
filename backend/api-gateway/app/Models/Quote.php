<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'quote_id',
        'user_id',
        'from_currency',
        'to_currency',
        'amount_sent',
        'amount_received',
        'rate',
        'fee',
        'route',
        'expires_at',
        'consumed_at',
        'metadata',
    ];

    protected $casts = [
        'amount_sent' => 'decimal:8',
        'amount_received' => 'decimal:8',
        'rate' => 'decimal:8',
        'fee' => 'decimal:8',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
