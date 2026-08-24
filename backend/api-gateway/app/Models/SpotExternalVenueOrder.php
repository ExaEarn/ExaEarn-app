<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotExternalVenueOrder extends Model
{
    protected $fillable = [
        'external_execution_id',
        'order_id',
        'market_id',
        'market_symbol',
        'venue',
        'client_order_id',
        'external_order_id',
        'side',
        'type',
        'quantity',
        'limit_price',
        'executed_quantity',
        'executed_quote_amount',
        'avg_execution_price',
        'status',
        'request_payload',
        'response_payload',
        'last_error',
    ];

    protected $casts = [
        'quantity' => 'decimal:18',
        'limit_price' => 'decimal:18',
        'executed_quantity' => 'decimal:18',
        'executed_quote_amount' => 'decimal:18',
        'avg_execution_price' => 'decimal:18',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];
}
