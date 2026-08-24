<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TradingIncident;
use App\Models\TradingIncidentEvent;
use Illuminate\Support\Str;

class TradingIncidentService
{
    public function open(
        string $type,
        string $severity,
        string $summary,
        ?string $scope = null,
        ?string $scopeKey = null,
        array $metadata = [],
    ): TradingIncident {
        $incident = TradingIncident::query()->create([
            'incident_id' => (string) Str::uuid(),
            'type' => strtoupper($type),
            'severity' => strtoupper($severity),
            'status' => 'OPEN',
            'scope' => $scope,
            'scope_key' => $scopeKey,
            'summary' => $summary,
            'metadata' => $metadata,
            'opened_at' => now(),
        ]);

        $this->event($incident, 'OPENED', $summary, $metadata);

        return $incident;
    }

    public function event(TradingIncident $incident, string $type, ?string $message = null, array $payload = []): TradingIncidentEvent
    {
        return TradingIncidentEvent::query()->create([
            'trading_incident_id' => $incident->id,
            'event_type' => strtoupper($type),
            'message' => $message,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
