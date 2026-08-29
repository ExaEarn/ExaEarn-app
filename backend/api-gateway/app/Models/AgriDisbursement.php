<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgriDisbursement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:18',
        'settled_at' => 'datetime',
        'metadata' => 'array',
    ];
}
