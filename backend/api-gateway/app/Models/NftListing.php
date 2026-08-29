<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NftListing extends Model
{
    protected $fillable = [
        'listing_uuid', 'nft_id', 'seller_user_id', 'seller_wallet', 'price_exa', 'listing_type',
        'status', 'listing_tx_hash', 'expires_at', 'metadata', 'settlement_asset', 'pricing_snapshot', 'idempotency_key',
    ];

    protected $casts = [
        'price_exa' => 'decimal:8',
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'pricing_snapshot' => 'array',
    ];

    public function nft(): BelongsTo
    {
        return $this->belongsTo(Nft::class, 'nft_id');
    }
}
