<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AIDecisionLog;
use App\Models\AISystemOverride;
use Illuminate\Support\Facades\Cache;

class SmartLiquidityService
{
    public function apply(string $symbol, array $aiOutput): array
    {
        $symbol = strtoupper($symbol);
        $override = AISystemOverride::query()
            ->where('enabled', true)
            ->where(function ($q) use ($symbol): void {
                $q->whereNull('symbol')->orWhere('symbol', $symbol);
            })
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        $manual = false;
        if ($override) {
            $manual = true;
            $aiOutput = array_merge($aiOutput, (array) $override->params);
        }

        $safe = $this->enforceSafety($aiOutput);

        Cache::put('ai_intel:mm:' . $symbol, $safe, now()->addMinutes(30));

        AIDecisionLog::query()->create([
            'symbol' => $symbol,
            'decision_type' => 'smart_liquidity',
            'inputs' => $aiOutput,
            'outputs' => $safe,
            'safety_applied' => ['clamped' => true],
            'manual_override' => $manual,
            'decided_at' => now(),
        ]);

        return $safe;
    }

    private function enforceSafety(array $decision): array
    {
        $minSpread = (float) config('ai_intel.safety.min_spread', 0.0005);
        $maxSpread = (float) config('ai_intel.safety.max_spread', 0.02);
        $minSize = (float) config('ai_intel.safety.min_order_size', 10);
        $maxSize = (float) config('ai_intel.safety.max_order_size', 50000);

        $spread = (float) ($decision['optimal_spread'] ?? 0.002);
        $size = (float) ($decision['order_size'] ?? 200);

        return [
            'optimal_spread' => max($minSpread, min($maxSpread, $spread)),
            'order_size' => max($minSize, min($maxSize, $size)),
            'risk_level' => (string) ($decision['risk_level'] ?? 'medium'),
            'trend' => (string) ($decision['trend'] ?? 'adaptive'),
        ];
    }
}
