<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PriceAnchorService
{
    public function anchor(string $symbol, float $internalPrice): float
    {
        $external = $this->fetchExternalPrice($symbol);
        if ($external <= 0) {
            return $internalPrice;
        }

        $maxDev = (float) config('market_maker.anchor.max_deviation_percent', 3.0);
        $dev = abs(($internalPrice - $external) / $external) * 100;

        if ($dev <= $maxDev) {
            return $internalPrice;
        }

        return $external;
    }

    private function fetchExternalPrice(string $symbol): float
    {
        try {
            $clean = str_replace('/', '', strtoupper($symbol));
            $res = Http::timeout(4)->get('https://api.binance.com/api/v3/ticker/price', ['symbol' => $clean]);
            if (!$res->ok()) {
                return 0.0;
            }
            return (float) ($res->json()['price'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
