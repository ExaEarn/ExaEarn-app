<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'account_type',
        'asset',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:18',
    ];
}
