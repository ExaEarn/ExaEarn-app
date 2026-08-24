<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopySurveillanceCase extends Model
{
    protected $fillable = [
        'case_id',
        'lead_trader_id',
        'copy_relationship_id',
        'signal_type',
        'severity',
        'evidence',
        'related_accounts',
        'markets',
        'orders',
        'trades',
        'copy_orders',
        'status',
        'reviewed_at',
        'reviewed_by',
        'resolution',
    ];

    protected $casts = [
        'evidence' => 'array',
        'related_accounts' => 'array',
        'markets' => 'array',
        'orders' => 'array',
        'trades' => 'array',
        'copy_orders' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
