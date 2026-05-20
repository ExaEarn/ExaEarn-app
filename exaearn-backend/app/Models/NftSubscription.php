<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftSubscription extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'plan', 'status', 'fee_exa', 'starts_at', 'ends_at', 'tx_hash', 'metadata',
    ];

    protected $casts = [
        'fee_exa' => 'decimal:8',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];
}
