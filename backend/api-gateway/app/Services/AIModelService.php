<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIModelService
{
    public function infer(string $symbol, array $history): array
    {
        $url = rtrim((string) config('ai_intel.python_service_url'), '/');
        if ($url !== '') {
            try {
                $res = Http::timeout(6)->post($url . '/infer', [
                    'symbol' => $symbol,
                    'history' => $history,
                ])->throw()->json();

                return [
                    'optimal_spread' => (float) ($res['optimal_spread'] ?? 0.002),
                    'order_size' => (float) ($res['order_size'] ?? 200),
                    'risk_level' => (string) ($res['risk_level'] ?? 'medium'),
                    'trend' => (string) ($res['trend'] ?? 'sideways'),
                ];
            } catch (\Throwable) {
            }
        }

        return $this->fallbackHeuristic($history);
    }

    private function fallbackHeuristic(array $history): array
    {
        if ($history === []) {
            return ['optimal_spread' => 0.002, 'order_size' => 200, 'risk_level' => 'medium', 'trend' => 'sideways'];
        }

        $prices = array_column($history, 'price');
        $volumes = array_column($history, 'volume');
        $avgPrice = array_sum($prices) / max(count($prices), 1);
        $avgVolume = array_sum($volumes) / max(count($volumes), 1);
        $priceStd = $this->stddev($prices);
        $volatility = $avgPrice > 0 ? $priceStd / $avgPrice : 0;

        $spread = $volatility > 0.01 ? 0.008 : ($volatility > 0.004 ? 0.004 : 0.0015);
        $orderSize = $volatility > 0.01 ? 120 : ($avgVolume > 100000 ? 800 : 250);
        $risk = $volatility > 0.01 ? 'high' : ($volatility > 0.004 ? 'medium' : 'low');

        return [
            'optimal_spread' => $spread,
            'order_size' => $orderSize,
            'risk_level' => $risk,
            'trend' => 'adaptive',
        ];
    }

    private function stddev(array $values): float
    {
        $count = count($values);
        if ($count <= 1) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return sqrt($sum / ($count - 1));
    }
}
