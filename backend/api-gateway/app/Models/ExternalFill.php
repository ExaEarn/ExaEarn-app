<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalFill extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:18',
        'quantity' => 'decimal:18',
        'quote_quantity' => 'decimal:18',
        'fee' => 'decimal:18',
        'filled_at' => 'datetime',
        'metadata' => 'array',
    ];
}
