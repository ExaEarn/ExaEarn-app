<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftSale extends Model
{
    protected $fillable = [
        'nft_id', 'listing_id', 'buyer_user_id', 'seller_user_id', 'buyer_wallet', 'seller_wallet',
        'sale_price_exa', 'platform_fee_exa', 'royalty_fee_exa', 'network_cost_exa', 'tx_hash', 'metadata', 'settlement_asset', 'status', 'idempotency_key', 'reservation_id',
    ];

    protected $casts = [
        'sale_price_exa' => 'decimal:8',
        'platform_fee_exa' => 'decimal:8',
        'royalty_fee_exa' => 'decimal:8',
        'network_cost_exa' => 'decimal:8',
        'metadata' => 'array',
    ];
}
