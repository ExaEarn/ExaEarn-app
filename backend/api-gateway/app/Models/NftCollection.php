<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NftCollection extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'creator_wallet',
        'royalty_percentage',
        'utility_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function nfts(): HasMany
    {
        return $this->hasMany(Nft::class, 'collection_id');
    }
}
