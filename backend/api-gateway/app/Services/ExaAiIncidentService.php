<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiOperationalIncident;
use Illuminate\Support\Str;

class ExaAiIncidentService
{
    public function open(string $severity, string $component, string $type, array $payload = []): ExaAiOperationalIncident
    {
        return ExaAiOperationalIncident::query()->firstOrCreate([
            'status' => 'OPEN',
            'component' => $component,
            'incident_type' => $type,
            'portfolio_id' => $payload['portfolio_id'] ?? null,
            'strategy_version_id' => $payload['strategy_version_id'] ?? null,
            'market_symbol' => $payload['market_symbol'] ?? null,
        ], [
            'incident_uuid' => (string) Str::uuid(),
            'severity' => strtoupper($severity),
            'expected_state' => $payload['expected_state'] ?? [],
            'actual_state' => $payload['actual_state'] ?? [],
            'difference' => $payload['difference'] ?? [],
        ]);
    }

    public function unresolvedCriticalExists(): bool
    {
        return ExaAiOperationalIncident::query()
            ->whereIn('severity', ['SEV1', 'SEV2'])
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED', 'MITIGATING', 'MONITORING'])
            ->exists();
    }
}
