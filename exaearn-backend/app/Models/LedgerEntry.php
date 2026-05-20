<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'wallet_type',
        'asset',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'reference_id',
        'transaction_type',
        'type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:18',
        'balance_after' => 'decimal:18',
    ];
}
