<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesOrder;
use App\Models\FuturesPosition;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerProfile;

class MarketMakerCrossProductRiskService
{
    public function __construct(private readonly MarketMakerInventoryService $inventory)
    {
    }

    public function exposure(MarketMakerBot $bot): array
    {
        $profile = MarketMakerProfile::query()->findOrFail($bot->market_maker_id);
        $spot = $this->inventory->snapshot($profile, $bot->market_symbol)->toArray();
        $base = (string) ($spot['base_asset'] ?? strtok($bot->market_symbol, '/'));
        $futuresMarket = (string) ($bot->configuration['futures_market_symbol'] ?? str_replace('/', '', $bot->market_symbol).'PERP');
        $institution = \App\Models\InstitutionalAccount::query()->findOrFail($bot->institution_id);

        $positions = FuturesPosition::query()
            ->where('user_id', $institution->master_user_id)
            ->where('symbol', $futuresMarket)
            ->where('status', 'open')
            ->get();

        $long = '0';
        $short = '0';
        $notional = '0';
        foreach ($positions as $position) {
            $quantity = FinancialDecimal::normalize((string) $position->quantity);
            $mark = FinancialDecimal::normalize((string) ($position->mark_price ?: $position->entry_price ?: '0'));
            $positionNotional = FinancialDecimal::mul($quantity, $mark);
            $notional = FinancialDecimal::add($notional, $positionNotional);
            if (strtolower((string) $position->side) === 'long') {
                $long = FinancialDecimal::add($long, $quantity);
            } else {
                $short = FinancialDecimal::add($short, $quantity);
            }
        }

        $openFutures = FuturesOrder::query()
            ->where('user_id', $institution->master_user_id)
            ->where('symbol', $futuresMarket)
            ->whereIn('status', ['open', 'partially_filled', 'pending_trigger'])
            ->count();
        $openSpot = \App\Models\Order::query()
            ->where('user_id', $institution->master_user_id)
            ->where('pair', $bot->market_symbol)
            ->whereIn('status', ['open', 'partially_filled', 'pending_trigger'])
            ->count();

        $spotBase = FinancialDecimal::normalize((string) ($spot['current_base_inventory'] ?? '0'));
        $netDelta = FinancialDecimal::sub(FinancialDecimal::add($spotBase, $long), $short);

        return [
            'base_asset' => $base,
            'spot_market' => $bot->market_symbol,
            'futures_market' => $futuresMarket,
            'spot_base_inventory' => $spotBase,
            'futures_long' => $long,
            'futures_short' => $short,
            'futures_notional' => $notional,
            'open_spot_orders' => $openSpot,
            'open_futures_orders' => $openFutures,
            'net_delta' => FinancialDecimal::normalize($netDelta),
            'source' => 'ledger_plus_futures_position_engine',
        ];
    }
}
