<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'user_id',
        'account_type',
        'asset',
        'balance',
        'status',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:18',
        'metadata' => 'array',
    ];
}
