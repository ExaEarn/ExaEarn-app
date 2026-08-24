<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingIncidentEvent extends Model
{
    protected $fillable = ['trading_incident_id', 'event_type', 'message', 'payload', 'occurred_at'];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];
}
