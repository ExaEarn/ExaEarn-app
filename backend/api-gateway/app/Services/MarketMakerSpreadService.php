<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerBot;

class MarketMakerSpreadService
{
    public function spreadBps(MarketMakerBot $bot, array $inventory): string
    {
        $config = $bot->configuration ?? [];
        $base = FinancialDecimal::normalize((string) ($config['base_spread_bps'] ?? '20'), 8);
        $max = FinancialDecimal::normalize((string) ($config['max_spread_bps'] ?? '100'), 8);
        $inventoryStatus = strtoupper((string) ($inventory['status'] ?? 'HEALTHY'));
        $adjustment = match ($inventoryStatus) {
            'WATCH' => '10',
            'LIMIT_EXCEEDED' => '40',
            default => '0',
        };
        $spread = FinancialDecimal::add($base, $adjustment, 8);

        return FinancialDecimal::compare($spread, $max, 8) > 0 ? $max : $spread;
    }
}
