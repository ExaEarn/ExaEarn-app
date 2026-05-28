<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AIRiskAlert;
use App\Models\Order;
use App\Models\Trade;

class AntiManipulationService
{
    public function scan(string $symbol): array
    {
        $alerts = [];

        $recentOrders = Order::query()
            ->where('pair', strtoupper($symbol))
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        $recentCancels = Order::query()
            ->where('pair', strtoupper($symbol))
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentOrders >= 20 && $recentCancels / max($recentOrders, 1) > 0.7) {
            $alerts[] = $this->alert($symbol, 'spoofing_pattern', 4, ['orders' => $recentOrders, 'cancels' => $recentCancels], 'restrict_trading');
        }

        $washCount = Trade::query()
            ->where('pair', strtoupper($symbol))
            ->whereColumn('buyer_id', 'seller_id')
            ->where('executed_at', '>=', now()->subHour())
            ->count();

        if ($washCount > 0) {
            $alerts[] = $this->alert($symbol, 'wash_trading', 5, ['trades' => $washCount], 'flag_user');
        }

        return $alerts;
    }

    private function alert(string $symbol, string $type, int $severity, array $details, string $action): array
    {
        $row = AIRiskAlert::query()->create([
            'symbol' => strtoupper($symbol),
            'alert_type' => $type,
            'severity' => $severity,
            'details' => $details,
            'action_taken' => $action,
            'detected_at' => now(),
        ]);

        return $row->toArray();
    }
}
