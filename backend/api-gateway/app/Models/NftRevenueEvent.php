<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftRevenueEvent extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'event_type', 'gross_amount_exa', 'platform_revenue_exa', 'token_burn_exa', 'tx_hash', 'metadata',
    ];

    protected $casts = [
        'gross_amount_exa' => 'decimal:8',
        'platform_revenue_exa' => 'decimal:8',
        'token_burn_exa' => 'decimal:8',
        'metadata' => 'array',
    ];
}
