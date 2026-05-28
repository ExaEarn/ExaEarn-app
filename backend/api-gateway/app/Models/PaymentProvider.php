<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    protected $fillable = [
        'code',
        'name',
        'countries',
        'currencies',
        'fee_percentage',
        'flat_fee',
        'status',
        'priority',
        'reliability',
        'metadata',
    ];

    protected $casts = [
        'countries' => 'array',
        'currencies' => 'array',
        'fee_percentage' => 'decimal:8',
        'flat_fee' => 'decimal:8',
        'reliability' => 'decimal:8',
        'metadata' => 'array',
    ];
}
