<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftUpgrade extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'from_tier', 'to_tier', 'from_level', 'to_level',
        'upgrade_fee_exa', 'burn_amount_exa', 'tx_hash', 'metadata',
    ];

    protected $casts = [
        'upgrade_fee_exa' => 'decimal:8',
        'burn_amount_exa' => 'decimal:8',
        'metadata' => 'array',
    ];
}
