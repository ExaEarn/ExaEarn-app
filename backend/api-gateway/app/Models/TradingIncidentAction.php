<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingIncidentAction extends Model
{
    protected $fillable = [
        'trading_incident_id', 'admin_id', 'action', 'reason',
        'before_state', 'after_state', 'performed_at',
    ];

    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'performed_at' => 'datetime'];
}
