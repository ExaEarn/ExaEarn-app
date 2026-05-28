<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftPrice extends Model
{
    protected $fillable = [
        'nft_id',
        'nft_uuid',
        'collection_name',
        'floor_price_usdt',
        'last_sale_price_usdt',
        'source',
        'last_updated',
    ];

    protected $casts = [
        'floor_price_usdt' => 'decimal:18',
        'last_sale_price_usdt' => 'decimal:18',
        'last_updated' => 'datetime',
    ];
}
