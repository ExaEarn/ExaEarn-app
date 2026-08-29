<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{
    protected $fillable = [
        'payout_uuid',
        'affiliate_user_id',
        'affiliate_payout_batch_id',
        'method',
        'asset',
        'amount',
        'status',
        'idempotency_key',
        'ledger_transaction_id',
        'requested_at',
        'approved_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];
}
