<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerBot;
use RuntimeException;

class MarketMakerQuoteEngine
{
    public function __construct(
        private readonly MarketMakerFairValueService $fairValue,
        private readonly MarketMakerSpreadService $spreads,
        private readonly MarketMakerInventoryService $inventory,
        private readonly MarketMakerBotRiskService $risk,
    ) {
    }

    public function plan(MarketMakerBot $bot): array
    {
        $fair = $this->fairValue->fairValue($bot->market_symbol);
        $inventorySnapshot = $this->inventory->snapshot(\App\Models\MarketMakerProfile::query()->findOrFail($bot->market_maker_id), $bot->market_symbol)->toArray();
        $risk = $this->risk->assertCanQuote($bot, $fair, $inventorySnapshot);
        $spreadBps = $this->spreads->spreadBps($bot, $inventorySnapshot);
        $config = $bot->configuration ?? [];
        $levels = max(1, min((int) ($config['levels'] ?? 1), 5));
        $size = FinancialDecimal::normalize((string) ($config['quote_size'] ?? $inventorySnapshot['target_quote_size'] ?? '0'));
        if (FinancialDecimal::compare($size, '0') <= 0) {
            throw new RuntimeException('Market-maker bot quote size must be configured above zero.');
        }

        $halfSpread = FinancialDecimal::div($spreadBps, '20000', 18);
        $quotes = [];
        for ($level = 1; $level <= $levels; $level++) {
            $levelMultiplier = (string) $level;
            $offset = FinancialDecimal::mul($halfSpread, $levelMultiplier, 18);
            $bid = FinancialDecimal::mul($fair['fair_value'], FinancialDecimal::sub('1', $offset, 18), 18);
            $ask = FinancialDecimal::mul($fair['fair_value'], FinancialDecimal::add('1', $offset, 18), 18);
            $levelSize = FinancialDecimal::div($size, $levelMultiplier, 18);
            $quotes[] = ['side' => 'BUY', 'price' => FinancialDecimal::normalize($bid), 'quantity' => $levelSize, 'level' => $level];
            $quotes[] = ['side' => 'SELL', 'price' => FinancialDecimal::normalize($ask), 'quantity' => $levelSize, 'level' => $level];
        }

        return [
            'fair_value' => $fair,
            'spread_bps' => $spreadBps,
            'inventory' => $inventorySnapshot,
            'risk' => $risk,
            'quotes' => $quotes,
            'ttl_seconds' => (int) ($config['quote_ttl_seconds'] ?? 30),
        ];
    }
}
