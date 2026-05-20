<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftCreditLine extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'credit_limit_exa', 'available_credit_exa', 'interest_bps',
        'liquidation_penalty_bps', 'credit_score', 'status', 'metadata',
    ];

    protected $casts = [
        'credit_limit_exa' => 'decimal:8',
        'available_credit_exa' => 'decimal:8',
        'interest_bps' => 'decimal:2',
        'liquidation_penalty_bps' => 'decimal:2',
        'metadata' => 'array',
    ];
}
