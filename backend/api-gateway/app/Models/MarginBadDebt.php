<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarginBadDebt extends Model
{
    protected $fillable = [
        'bad_debt_id',
        'margin_account_id',
        'user_id',
        'asset',
        'amount',
        'covered_amount',
        'status',
        'ledger_reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'covered_amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
