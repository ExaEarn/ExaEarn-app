<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nft extends Model
{
    protected $table = 'nfts';

    protected $fillable = [
        'nft_uuid', 'token_id', 'contract_address', 'collection_id', 'user_id', 'utility_type', 'name', 'symbol',
        'creator_wallet', 'owner_wallet', 'tier', 'level', 'status', 'mint_fee_exa', 'current_value_exa',
        'earnings_generated_exa', 'metadata_url', 'mint_tx_hash', 'last_event_tx_hash', 'last_synced_at',
        'benefits', 'upgrade_options', 'metadata',
    ];

    protected $casts = [
        'mint_fee_exa' => 'decimal:8',
        'current_value_exa' => 'decimal:8',
        'earnings_generated_exa' => 'decimal:8',
        'last_synced_at' => 'datetime',
        'benefits' => 'array',
        'upgrade_options' => 'array',
        'metadata' => 'array',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(NftCollection::class, 'collection_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(NftListing::class, 'nft_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(NftSale::class, 'nft_id');
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(NftAuction::class, 'nft_id');
    }
}
