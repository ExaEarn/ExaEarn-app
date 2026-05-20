<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AIRiskAlert;

class AIRiskDetectionService
{
    public function detectMarketAnomaly(string $symbol, array $points): ?array
    {
        if (count($points) < 10) {
            return null;
        }

        $last = end($points);
        $avgVolume = array_sum(array_column($points, 'volume')) / count($points);
        $avgSpread = array_sum(array_column($points, 'spread')) / count($points);

        $isSpike = ((float) ($last['volume'] ?? 0)) > ($avgVolume * 3);
        $isSpreadShock = ((float) ($last['spread'] ?? 0)) > ($avgSpread * 2.5);

        if (!$isSpike && !$isSpreadShock) {
            return null;
        }

        $severity = $isSpike && $isSpreadShock ? 5 : 3;
        $action = $severity >= 5 ? 'restrict_trading' : 'adjust_risk_parameters';

        $alert = AIRiskAlert::query()->create([
            'symbol' => strtoupper($symbol),
            'alert_type' => $isSpike ? 'abnormal_trading_spike' : 'spread_shock',
            'severity' => $severity,
            'details' => [
                'avg_volume' => $avgVolume,
                'last_volume' => $last['volume'] ?? 0,
                'avg_spread' => $avgSpread,
                'last_spread' => $last['spread'] ?? 0,
            ],
            'action_taken' => $action,
            'detected_at' => now(),
        ]);

        return $alert->toArray();
    }
}
