<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreService;
use Illuminate\Support\Str;

class SreServiceRegistry
{
    public function register(array $data): SreService
    {
        return SreService::query()->updateOrCreate([
            'service_id' => $data['service_id'],
        ], [
            'service_uuid' => (string) (SreService::query()->where('service_id', $data['service_id'])->value('service_uuid') ?: Str::uuid()),
            'service_name' => $data['service_name'],
            'service_type' => strtoupper($data['service_type']),
            'criticality' => strtoupper($data['criticality'] ?? 'TIER_2'),
            'environment' => strtoupper($data['environment'] ?? app()->environment()),
            'version' => $data['version'] ?? config('app.version'),
            'deployment_id' => $data['deployment_id'] ?? env('DEPLOYMENT_ID'),
            'region' => $data['region'] ?? env('APP_REGION', 'local'),
            'dependencies' => $data['dependencies'] ?? [],
            'health_endpoint' => $data['health_endpoint'] ?? null,
            'readiness_endpoint' => $data['readiness_endpoint'] ?? null,
            'heartbeat_at' => now(),
            'last_seen_at' => now(),
            'status' => strtoupper($data['status'] ?? 'HEALTHY'),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function seedCore(): array
    {
        $services = [
            ['service_id' => 'api-gateway', 'service_name' => 'Laravel API Gateway', 'service_type' => 'API', 'criticality' => 'TIER_0', 'dependencies' => ['postgresql', 'redis', 'queue']],
            ['service_id' => 'canonical-ledger', 'service_name' => 'Canonical Ledger', 'service_type' => 'FINANCE', 'criticality' => 'TIER_0', 'dependencies' => ['postgresql']],
            ['service_id' => 'spot-engine', 'service_name' => 'Spot Trading Engine', 'service_type' => 'TRADING', 'criticality' => 'TIER_1', 'dependencies' => ['postgresql', 'redis', 'market-data', 'risk', 'ledger']],
            ['service_id' => 'custody', 'service_name' => 'Custody Operations', 'service_type' => 'CUSTODY', 'criticality' => 'TIER_1', 'dependencies' => ['postgresql', 'queue', 'rpc', 'signing']],
            ['service_id' => 'fiat', 'service_name' => 'Fiat Provider Rails', 'service_type' => 'PAYMENTS', 'criticality' => 'TIER_2', 'dependencies' => ['postgresql', 'queue', 'payment-provider']],
            ['service_id' => 'market-data', 'service_name' => 'Market Data', 'service_type' => 'MARKET_DATA', 'criticality' => 'TIER_1', 'dependencies' => ['postgresql', 'redis', 'external-reference']],
            ['service_id' => 'security', 'service_name' => 'Security Operations', 'service_type' => 'SECURITY', 'criticality' => 'TIER_0', 'dependencies' => ['postgresql']],
            ['service_id' => 'finance', 'service_name' => 'Finance Reconciliation', 'service_type' => 'FINANCE', 'criticality' => 'TIER_0', 'dependencies' => ['postgresql']],
        ];

        return array_map(fn (array $service) => $this->register($service), $services);
    }

    public function all(): array
    {
        return SreService::query()->orderBy('criticality')->orderBy('service_id')->get()->all();
    }
}
