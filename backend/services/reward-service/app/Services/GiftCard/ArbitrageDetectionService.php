<?php

namespace App\Services\GiftCard;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class ArbitrageDetectionService
{
    public function __construct(private readonly RateEngineService $rateEngine)
    {
    }

    public function detectArbitrage(string $brand, float $cardValue): array
    {
        $rates = $this->rateEngine->getRates($brand, $cardValue);
        $opportunities = [];

        if ($rates['external_buy_rate'] > 0 && $rates['external_buy_rate'] < $rates['sell_rate']) {
            $profit = ($rates['sell_rate'] - $rates['external_buy_rate']) * $rates['card_value'];
            $opportunities[] = [
                'type' => 'buy_external_sell_internal',
                'signal' => 'Buy externally, sell internally',
                'profit_margin' => round(($rates['sell_rate'] - $rates['external_buy_rate']) / $rates['sell_rate'], 4),
                'internal_sell_rate' => $rates['sell_rate'],
                'external_buy_rate' => $rates['external_buy_rate'],
                'potential_profit' => round($profit, 2),
            ];
        }

        if ($rates['buy_rate'] < $rates['external_sell_rate']) {
            $profit = ($rates['external_sell_rate'] - $rates['buy_rate']) * $rates['card_value'];
            $opportunities[] = [
                'type' => 'buy_user_sell_external',
                'signal' => 'Buy from user, resell externally',
                'profit_margin' => round(($rates['external_sell_rate'] - $rates['buy_rate']) / $rates['external_sell_rate'], 4),
                'user_sell_rate' => $rates['buy_rate'],
                'external_sell_rate' => $rates['external_sell_rate'],
                'potential_profit' => round($profit, 2),
            ];
        }

        if ($opportunities !== []) {
            $payload = [
                'brand' => $rates['brand'],
                'card_value' => $rates['card_value'],
                'currency' => $rates['currency'],
                'opportunities' => $opportunities,
                'detected_at' => now()->toISOString(),
            ];
            Log::info('giftcard.arbitrage.detected', $payload);
            Event::dispatch('arbitrage.detected', $payload);
        }

        return $opportunities;
    }

    public function getAllArbitrageOpportunities(): array
    {
        return collect(array_keys(config('giftcard_arbitrage.brands', [])))
            ->mapWithKeys(fn (string $brand) => [$brand => $this->detectArbitrage($brand, 100)])
            ->filter()
            ->all();
    }
}
