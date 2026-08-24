<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotIncident;
use App\Models\MarketMakerBotQuoteCycle;
use Illuminate\Support\Str;

class MarketMakerMarketShockService
{
    public function __construct(
        private readonly MarketMakerFairValueService $fairValue,
        private readonly MarketMakerCancelReplaceService $cancels,
    ) {
    }

    public function evaluate(MarketMakerBot $bot): array
    {
        $fair = $this->fairValue->fairValue($bot->market_symbol);
        $previous = MarketMakerBotQuoteCycle::query()
            ->where('bot_id', $bot->id)
            ->whereNotNull('fair_value')
            ->latest()
            ->first();
        $threshold = FinancialDecimal::normalize((string) ($bot->risk_limits['market_shock_bps'] ?? '1000'), 8);
        $moveBps = '0.00000000';
        if ($previous && FinancialDecimal::compare((string) $previous->fair_value, '0') > 0) {
            $moveBps = FinancialDecimal::mul(
                FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub((string) $fair['fair_value'], (string) $previous->fair_value)), (string) $previous->fair_value),
                '10000',
                8
            );
        }
        $shock = FinancialDecimal::compare($moveBps, $threshold, 8) >= 0 || ($fair['market_data_status'] ?? '') === 'STALE';
        $response = 'NORMAL';
        $cancelResult = ['cancelled' => [], 'failed' => []];
        if ($shock) {
            $response = FinancialDecimal::compare($moveBps, FinancialDecimal::mul($threshold, '2', 8), 8) >= 0 ? 'PAUSED' : 'LIMIT_NEW_RISK';
            $bot->forceFill(['safety_state' => $response, 'status' => $response === 'PAUSED' ? 'PAUSED' : 'LIMIT_NEW_RISK'])->save();
            $cancelResult = $this->cancels->massCancel($bot->fresh(), 'market_shock');
            MarketMakerBotIncident::query()->create([
                'incident_uuid' => (string) Str::uuid(),
                'bot_id' => $bot->id,
                'category' => 'MARKET_SHOCK',
                'severity' => $response === 'PAUSED' ? 'CRITICAL' : 'HIGH',
                'status' => 'OPEN',
                'title' => 'Market-maker bot market shock protection activated.',
                'evidence' => ['move_bps' => $moveBps, 'threshold_bps' => $threshold, 'fair_value' => $fair, 'cancel_result' => $cancelResult],
                'opened_at' => now(),
            ]);
        }

        return [
            'status' => $shock ? 'SHOCK_DETECTED' : 'STABLE',
            'response' => $response,
            'move_bps' => $moveBps,
            'threshold_bps' => $threshold,
            'cancel_result' => $cancelResult,
        ];
    }
}
