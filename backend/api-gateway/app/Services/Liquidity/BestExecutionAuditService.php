<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\BestExecutionAudit;
use Illuminate\Support\Str;

class BestExecutionAuditService
{
    public function record(array $payload): BestExecutionAudit
    {
        return BestExecutionAudit::query()->create([
            'audit_id' => (string) Str::uuid(),
            'route_plan_id' => $payload['route_plan_id'] ?? null,
            'parent_reference' => (string) $payload['parent_reference'],
            'market_symbol' => strtoupper((string) $payload['market_symbol']),
            'side' => strtolower((string) $payload['side']),
            'requested_quantity' => (string) $payload['requested_quantity'],
            'market_state' => $payload['market_state'] ?? [],
            'sources_considered' => $payload['sources_considered'] ?? [],
            'route_selected' => $payload['route_selected'] ?? [],
            'result' => $payload['result'] ?? null,
            'quality_score' => (string) ($payload['quality_score'] ?? '0'),
            'status' => (string) ($payload['status'] ?? 'RECORDED'),
        ]);
    }
}
