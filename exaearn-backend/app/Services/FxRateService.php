<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FxRateService
{
    public function getRate(string $base, string $quote): string
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            return '1';
        }

        $cacheKey = "fx_rate:{$base}:{$quote}";
        $ttl = 20;

        return Cache::remember($cacheKey, $ttl, function () use ($base, $quote): string {
            $url = rtrim((string) config('services.fx.url', 'https://open.er-api.com/v6/latest'), '/');
            $response = Http::timeout(8)->get("{$url}/{$base}");

            if (!$response->ok()) {
                throw new RuntimeException('Unable to fetch FX rates.');
            }

            $rate = (string) data_get($response->json(), "rates.{$quote}");
            if ($rate === '' || $rate === null) {
                throw new RuntimeException("FX pair {$base}/{$quote} unavailable.");
            }

            return $this->applySpread($rate);
        });
    }

    private function applySpread(string $rate): string
    {
        $spreadPercent = (string) config('swap.fx_spread_percent', '1.2');
        $multiplier = $this->div($this->add('100', $spreadPercent), '100');

        return $this->mul($rate, $multiplier);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 8) : number_format(((float) $a + (float) $b), 8, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, 8) : number_format(((float) $a * (float) $b), 8, '.', '');
    }

    private function div(string $a, string $b): string
    {
        return function_exists('bcdiv') ? bcdiv($a, $b, 8) : number_format(((float) $a / (float) $b), 8, '.', '');
    }
}
