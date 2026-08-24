<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingIncident extends Model
{
    protected $fillable = [
        'incident_id', 'type', 'severity', 'status', 'scope', 'scope_key',
        'summary', 'metadata', 'opened_at', 'resolved_at',
    ];

    protected $casts = ['metadata' => 'array', 'opened_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function events(): HasMany
    {
        return $this->hasMany(TradingIncidentEvent::class);
    }
}
