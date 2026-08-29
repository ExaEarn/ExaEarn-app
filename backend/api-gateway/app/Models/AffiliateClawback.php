<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateClawback extends Model
{
    protected $fillable = [
        'clawback_uuid',
        'affiliate_commission_event_id',
        'affiliate_user_id',
        'reversal_reference',
        'amount',
        'asset',
        'reason_code',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'metadata' => 'array',
    ];
}
