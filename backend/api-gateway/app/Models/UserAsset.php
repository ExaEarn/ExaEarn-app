<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAsset extends Model
{
    protected $fillable = [
        'user_id',
        'asset_type',
        'asset_symbol',
        'balance',
        'locked_balance',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:18',
        'locked_balance' => 'decimal:18',
        'metadata' => 'array',
    ];
}
