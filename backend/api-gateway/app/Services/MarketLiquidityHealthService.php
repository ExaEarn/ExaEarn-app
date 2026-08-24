<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketLiquidityHealthSnapshot;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerQuote;
use Illuminate\Support\Str;

class MarketLiquidityHealthService
{
    public function snapshot(string $marketSymbol): MarketLiquidityHealthSnapshot
    {
        $symbol = strtoupper($marketSymbol);
        $bids = MarketMakerQuote::query()->where('market_symbol', $symbol)->where('side', 'BUY')->where('status', 'ACTIVE');
        $asks = MarketMakerQuote::query()->where('market_symbol', $symbol)->where('side', 'SELL')->where('status', 'ACTIVE');
        $bestBid = (string) (((clone $bids)->max('price')) ?: '0');
        $bestAsk = (string) (((clone $asks)->min('price')) ?: '0');
        $bidDepth = FinancialDecimal::normalize((string) (((clone $bids)->sum('quantity')) ?: '0'));
        $askDepth = FinancialDecimal::normalize((string) (((clone $asks)->sum('quantity')) ?: '0'));
        $makerCount = MarketMakerMarketAssignment::query()->where('market_symbol', $symbol)->where('status', 'ACTIVE')->distinct('market_maker_id')->count('market_maker_id');
        $assignment = MarketMakerMarketAssignment::query()->where('market_symbol', $symbol)->where('status', 'ACTIVE')->latest()->first();
        $requiredDepth = FinancialDecimal::normalize((string) ($assignment?->minimum_depth ?? '0'));
        $maxSpread = FinancialDecimal::normalize((string) ($assignment?->maximum_spread_bps ?? '100'), 8);

        $spread = null;
        $reasons = [];
        if (FinancialDecimal::compare($bestBid, '0') > 0 && FinancialDecimal::compare($bestAsk, '0') > 0) {
            $mid = FinancialDecimal::div(FinancialDecimal::add($bestBid, $bestAsk), '2');
            $spread = FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::sub($bestAsk, $bestBid), $mid, 18), '10000', 8);
        } else {
            $reasons[] = 'MISSING_TWO_SIDED_QUOTES';
        }

        if ($spread !== null && FinancialDecimal::compare($spread, $maxSpread, 8) > 0) {
            $reasons[] = 'SPREAD_TOO_WIDE';
        }
        if (FinancialDecimal::compare(FinancialDecimal::min($bidDepth, $askDepth), $requiredDepth) < 0) {
            $reasons[] = 'DEPTH_BELOW_REQUIREMENT';
        }
        $status = empty($reasons) ? 'HEALTHY' : 'DEGRADED';

        return MarketLiquidityHealthSnapshot::query()->create([
            'snapshot_uuid' => (string) Str::uuid(),
            'market_symbol' => $symbol,
            'status' => $status,
            'best_bid' => FinancialDecimal::compare($bestBid, '0') > 0 ? $bestBid : null,
            'best_ask' => FinancialDecimal::compare($bestAsk, '0') > 0 ? $bestAsk : null,
            'spread_bps' => $spread,
            'bid_depth' => $bidDepth,
            'ask_depth' => $askDepth,
            'quote_presence' => empty($reasons) ? '100' : '0',
            'market_maker_count' => $makerCount,
            'score' => empty($reasons) ? '100' : '50',
            'reasons' => $reasons,
            'measured_at' => now(),
        ]);
    }
}
