<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerReversalLink extends Model
{
    protected $fillable = [
        'original_transaction_id',
        'reversal_transaction_id',
        'reason',
        'performed_by_type',
        'performed_by_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
