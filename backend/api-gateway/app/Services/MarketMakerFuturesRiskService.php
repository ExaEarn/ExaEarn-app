<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotIncident;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerFuturesRiskService
{
    public function assertCanQuote(MarketMakerBot $bot, array $fairValue, array $crossRisk, bool $reduceOnly = false): array
    {
        $limits = $bot->risk_limits ?? [];
        $marketSymbol = (string) ($bot->configuration['futures_market_symbol'] ?? str_replace('/', '', $bot->market_symbol).'PERP');
        $market = FuturesMarket::query()->where('symbol', $marketSymbol)->firstOrFail();
        $mark = FinancialDecimal::normalize((string) ($market->mark_price ?: $market->last_price));
        $index = FinancialDecimal::normalize((string) ($market->index_price ?: $mark));
        $divergence = FinancialDecimal::compare($index, '0') > 0
            ? FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub($mark, $index)), $index), '10000', 8)
            : '0.00000000';
        $maxDivergence = FinancialDecimal::normalize((string) ($limits['max_mark_index_divergence_bps'] ?? '250'), 8);
        $maxLeverage = (int) ($limits['max_futures_leverage'] ?? min(3, (int) $market->max_leverage));
        $leverage = (int) ($bot->configuration['futures_leverage'] ?? 1);

        $blocked = [];
        if ($market->status !== 'active') {
            $blocked[] = 'futures_market_not_active';
        }
        if ($leverage < 1 || $leverage > $maxLeverage || $leverage > (int) $market->max_leverage) {
            $blocked[] = 'leverage_limit';
        }
        if (FinancialDecimal::compare($divergence, $maxDivergence, 8) > 0) {
            $blocked[] = 'mark_index_divergence';
        }
        if (($fairValue['market_data_status'] ?? 'UNKNOWN') === 'STALE') {
            $blocked[] = 'stale_market_data';
        }
        if (! $reduceOnly && in_array($bot->safety_state, ['REDUCE_ONLY', 'PAUSED', 'EMERGENCY'], true)) {
            $blocked[] = 'safety_state_blocks_new_risk';
        }

        $snapshot = [
            'status' => $blocked === [] ? 'PASS' : 'BLOCKED',
            'futures_market' => $marketSymbol,
            'mark_price' => $mark,
            'index_price' => $index,
            'funding_rate' => (string) ($market->funding_rate ?? '0'),
            'basis_risk_bps' => $divergence,
            'max_divergence_bps' => $maxDivergence,
            'leverage' => $leverage,
            'max_leverage' => $maxLeverage,
            'cross_product' => $crossRisk,
            'blocked_reasons' => $blocked,
        ];
        if ($blocked !== []) {
            MarketMakerBotIncident::query()->create([
                'incident_uuid' => (string) Str::uuid(),
                'bot_id' => $bot->id,
                'category' => 'FUTURES_RISK_BLOCK',
                'severity' => 'HIGH',
                'status' => 'OPEN',
                'title' => 'Futures market-maker risk check blocked quoting.',
                'evidence' => $snapshot,
                'opened_at' => now(),
            ]);
            throw new RuntimeException('Futures market-maker risk check failed: '.implode(', ', $blocked));
        }

        return $snapshot;
    }
}
