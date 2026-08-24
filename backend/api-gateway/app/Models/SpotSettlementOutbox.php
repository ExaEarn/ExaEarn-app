<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotSettlementOutbox extends Model
{
    protected $table = 'spot_settlement_outbox';

    protected $fillable = [
        'outbox_id',
        'execution_id',
        'trade_id',
        'reference',
        'status',
        'attempts',
        'payload',
        'last_error',
        'settled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'settled_at' => 'datetime',
    ];
}
