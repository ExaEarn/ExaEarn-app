<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftAuction extends Model
{
    protected $fillable = [
        'auction_uuid', 'nft_id', 'seller_user_id', 'seller_wallet', 'starting_price_exa',
        'current_highest_bid_exa', 'highest_bidder_user_id', 'highest_bidder_wallet', 'status',
        'auction_tx_hash', 'starts_at', 'ends_at', 'metadata',
    ];

    protected $casts = [
        'starting_price_exa' => 'decimal:8',
        'current_highest_bid_exa' => 'decimal:8',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];
}
