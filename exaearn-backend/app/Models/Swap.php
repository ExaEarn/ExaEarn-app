<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Swap extends Model
{
    protected $fillable = [
        'swap_id',
        'user_id',
        'quote_id',
        'from_currency',
        'to_currency',
        'amount_sent',
        'amount_received',
        'rate',
        'fee',
        'status',
        'idempotency_key',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount_sent' => 'decimal:8',
        'amount_received' => 'decimal:8',
        'rate' => 'decimal:8',
        'fee' => 'decimal:8',
        'metadata' => 'array',
    ];
}
