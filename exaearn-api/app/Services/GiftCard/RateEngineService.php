<?php

namespace App\Services\GiftCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class RateEngineService
{
    public function getRates(string $brand, float $cardValue): array
    {
        $brand = $this->normalizeBrand($brand);
        $cardValue = $this->normalizeValue($cardValue);
        $cacheKey = $this->rateCacheKey($brand, $cardValue);
        $ttl = (int) config('giftcard_arbitrage.rate_ttl_seconds', 45);

        return $this->cache()->remember($cacheKey, $ttl, fn () => $this->calculateRates($brand, $cardValue));
    }

    public function calculateRates(string $brand, float $cardValue): array
    {
        $brand = $this->normalizeBrand($brand);
        $cardValue = $this->normalizeValue($cardValue);
        $config = $this->brandConfig($brand);

        $marketBuyRate = (float) $config['market_buy_rate'];
        $marketSellRate = (float) $config['market_sell_rate'];
        $inventoryLevel = $this->metricLevel($brand, 'inventory_level', (float) $config['inventory_level']);
        $demandLevel = $this->metricLevel($brand, 'demand_level', (float) $config['demand_level']);
        $minThreshold = (float) $config['min_threshold'];
        $maxThreshold = (float) $config['max_threshold'];

        $buyRate = $marketBuyRate;
        $sellRate = $marketSellRate;

        if ($inventoryLevel < $minThreshold) {
            $buyRate *= 1 + (($minThreshold - $inventoryLevel) * 0.14);
        } elseif ($inventoryLevel > $maxThreshold) {
            $buyRate *= 1 - (($inventoryLevel - $maxThreshold) * 0.18);
        } else {
            $buyRate *= 1 + ((0.5 - $inventoryLevel) * 0.035);
        }

        $sellRate *= 1 + (($demandLevel - 0.5) * 0.07);

        [$externalBuyRate, $externalSellRate] = $this->externalRates($brand);
        $band = (float) config('giftcard_arbitrage.competitive_band_pct', 0.025);
        if ($externalSellRate > 0) {
            $buyRate = min($buyRate, $externalSellRate * (1 - ($band / 2)));
        }
        if ($externalBuyRate > 0) {
            $sellRate = min($sellRate, $externalBuyRate * (1 + $band));
            $sellRate = max($sellRate, $externalBuyRate * (1 - $band));
        }

        $buyRate = $this->capVolatility($brand, $marketBuyRate, $buyRate);
        $sellRate = $this->capVolatility($brand, $marketSellRate, $sellRate);

        $minProfitMargin = (float) ($config['min_profit_margin'] ?? config('giftcard_arbitrage.default_min_profit_margin', 0.045));
        $minimumSpread = max(1, $buyRate * $minProfitMargin);
        if (($sellRate - $buyRate) < $minimumSpread) {
            $sellRate = $buyRate + $minimumSpread;
        }

        $buyRate = round(max((float) $config['fallback_buy_rate'] * 0.92, $buyRate), 2);
        $sellRate = round(max($buyRate + $minimumSpread, $sellRate), 2);
        $spread = round($sellRate - $buyRate, 2);
        $payout = round($cardValue * $buyRate, 2);
        $price = round($cardValue * $sellRate, 2);

        $rates = [
            'brand' => $brand,
            'brand_label' => $config['label'] ?? ucfirst(str_replace('_', ' ', $brand)),
            'card_value' => $cardValue,
            'currency' => 'NGN',
            'market_buy_rate' => round($marketBuyRate, 2),
            'market_sell_rate' => round($marketSellRate, 2),
            'external_buy_rate' => round($externalBuyRate, 2),
            'external_sell_rate' => round($externalSellRate, 2),
            'buy_rate' => $buyRate,
            'sell_rate' => $sellRate,
            'spread' => $spread,
            'min_profit_margin' => $minProfitMargin,
            'payout' => $payout,
            'price' => $price,
            'platform_profit' => round($price - $payout, 2),
            'demand_level' => $this->levelLabel($demandLevel),
            'demand_score' => round($demandLevel, 2),
            'inventory_status' => $this->inventoryStatus($inventoryLevel, $minThreshold, $maxThreshold),
            'inventory_level' => round($inventoryLevel, 2),
            'lock_duration' => (int) config('giftcard_arbitrage.lock_duration_seconds', 60),
            'rate_ttl' => (int) config('giftcard_arbitrage.rate_ttl_seconds', 45),
            'stale_after' => now()->addSeconds((int) config('giftcard_arbitrage.rate_ttl_seconds', 45))->toISOString(),
            'last_updated' => now()->toISOString(),
        ];

        Log::info('giftcard.rate.updated', $rates);
        Event::dispatch('rate.updated', $rates);

        if ($inventoryLevel < $minThreshold) {
            Event::dispatch('inventory.low', ['brand' => $brand, 'inventory_level' => $inventoryLevel]);
        }

        return $rates;
    }

    public function updateInventoryLevel(string $brand, float $level): void
    {
        $this->putMetric($brand, 'inventory_level', $level);
    }

    public function updateDemandLevel(string $brand, float $level): void
    {
        $this->putMetric($brand, 'demand_level', $level);
    }

    public function overrideRates(string $brand, array $rates): array
    {
        $brand = $this->normalizeBrand($brand);
        $allowed = ['market_buy_rate', 'market_sell_rate', 'external_buy_rate', 'external_sell_rate', 'min_profit_margin'];
        $overrides = array_intersect_key($rates, array_flip($allowed));
        $this->cache()->put("giftcard:rate-overrides:{$brand}", $overrides, now()->addDay());
        $this->clearBrandCache($brand);

        Log::warning('giftcard.rate.override', ['brand' => $brand, 'overrides' => $overrides]);

        return $this->getRates($brand, 100);
    }

    public function getAllRates(float $referenceValue = 100): array
    {
        return collect(array_keys(config('giftcard_arbitrage.brands', [])))
            ->mapWithKeys(fn (string $brand) => [$brand => $this->getRates($brand, $referenceValue)])
            ->all();
    }

    public function clearBrandCache(string $brand): void
    {
        $brand = $this->normalizeBrand($brand);
        foreach ([25, 50, 100, 200, 500, 1000] as $value) {
            $this->cache()->forget($this->rateCacheKey($brand, (float) $value));
        }
    }

    public function normalizeBrand(string $brand): string
    {
        $brand = strtolower(trim(str_replace([' ', '-'], '_', $brand)));
        return $brand === 'googleplay' ? 'google_play' : $brand;
    }

    private function brandConfig(string $brand): array
    {
        $brands = config('giftcard_arbitrage.brands', []);
        $config = $brands[$brand] ?? $brands['amazon'];
        $overrides = $this->cache()->get("giftcard:rate-overrides:{$brand}", []);

        return array_replace($config, is_array($overrides) ? $overrides : []);
    }

    private function externalRates(string $brand): array
    {
        $config = $this->brandConfig($brand);
        return [(float) ($config['external_buy_rate'] ?? 0), (float) ($config['external_sell_rate'] ?? 0)];
    }

    private function capVolatility(string $brand, float $reference, float $candidate): float
    {
        $cap = (float) config('giftcard_arbitrage.max_volatility_pct', 0.035);
        $min = $reference * (1 - $cap);
        $max = $reference * (1 + $cap);

        return max($min, min($max, $candidate));
    }

    private function metricLevel(string $brand, string $metric, float $fallback): float
    {
        return max(0, min(1, (float) $this->cache()->get("giftcard:metric:{$brand}:{$metric}", $fallback)));
    }

    private function putMetric(string $brand, string $metric, float $level): void
    {
        $brand = $this->normalizeBrand($brand);
        $level = max(0, min(1, $level));
        $this->cache()->put("giftcard:metric:{$brand}:{$metric}", $level, now()->addDay());
        $this->clearBrandCache($brand);
        Log::info('giftcard.metric.updated', compact('brand', 'metric', 'level'));
    }

    private function levelLabel(float $level): string
    {
        return $level >= 0.7 ? 'High' : ($level <= 0.35 ? 'Low' : 'Medium');
    }

    private function inventoryStatus(float $level, float $minThreshold, float $maxThreshold): string
    {
        if ($level < $minThreshold) {
            return 'Limited';
        }
        if ($level > $maxThreshold) {
            return 'Surplus';
        }

        return 'Available';
    }

    private function rateCacheKey(string $brand, float $cardValue): string
    {
        return sprintf('giftcard:rates:%s:%s', $this->normalizeBrand($brand), number_format($cardValue, 2, '.', ''));
    }

    private function normalizeValue(float $cardValue): float
    {
        return round(max(1, $cardValue), 2);
    }

    private function cache()
    {
        return Cache::store(config('giftcard_arbitrage.cache_store'));
    }
}
