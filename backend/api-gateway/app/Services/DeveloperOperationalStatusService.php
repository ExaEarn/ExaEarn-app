<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreHealthSnapshot;
use App\Models\SreService;

class DeveloperOperationalStatusService
{
    private const COMPONENTS = [
        'REST_API' => ['api', 'gateway'],
        'AUTHENTICATION' => ['auth', 'identity'],
        'MARKET_DATA' => ['market', 'data'],
        'WEBSOCKET' => ['realtime', 'websocket'],
        'SPOT' => ['spot'],
        'FUTURES' => ['futures'],
        'WALLET' => ['wallet', 'custody'],
        'SANDBOX' => ['sandbox'],
        'WEBHOOKS' => ['webhook'],
    ];

    public function publicStatus(): array
    {
        $snapshot = SreHealthSnapshot::query()->latest('captured_at')->first();
        $services = SreService::query()->get(['service_id', 'status']);

        return [
            'overall_status' => $this->normalize($snapshot?->overall_status),
            'components' => collect(self::COMPONENTS)->map(function (array $needles) use ($services): string {
                $matches = $services->filter(function (SreService $service) use ($needles): bool {
                    $id = strtolower((string) $service->service_id);

                    return collect($needles)->contains(fn (string $needle): bool => str_contains($id, $needle));
                });

                if ($matches->isEmpty()) {
                    return 'UNKNOWN';
                }

                return $matches->map(fn (SreService $service): string => $this->normalize($service->status))
                    ->sortBy(fn (string $status): int => $this->severity($status))
                    ->last();
            })->all(),
            'updated_at' => $snapshot?->captured_at?->toISOString(),
        ];
    }

    private function normalize(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'HEALTHY', 'PASS', 'READY', 'OPERATIONAL' => 'OPERATIONAL',
            'DEGRADED', 'WARNING' => 'DEGRADED',
            'PARTIAL_OUTAGE', 'PARTIAL' => 'PARTIAL_OUTAGE',
            'MAJOR_OUTAGE', 'CRITICAL', 'FAILED', 'FAIL', 'DOWN' => 'MAJOR_OUTAGE',
            default => 'UNKNOWN',
        };
    }

    private function severity(string $status): int
    {
        return match ($status) {
            'OPERATIONAL' => 0,
            'UNKNOWN' => 1,
            'DEGRADED' => 2,
            'PARTIAL_OUTAGE' => 3,
            'MAJOR_OUTAGE' => 4,
            default => 1,
        };
    }
}
