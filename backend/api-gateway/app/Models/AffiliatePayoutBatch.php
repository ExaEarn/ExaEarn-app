<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliatePayoutBatch extends Model
{
    protected $fillable = [
        'batch_uuid',
        'status',
        'asset',
        'total_amount',
        'item_count',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
