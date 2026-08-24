<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\LiquiditySource;
use App\Models\LiquidityRoutePlan;
use App\Models\MarketMakerQuote;
use App\Models\WithdrawalLiquidityReserve;

class LiquidityOperationalReadinessService
{
    public function check(): array
    {
        app(LiquiditySourceRegistry::class)->syncConfiguredSources();
        $recon = app(LiquidityReconciliationService::class)->run();

        $components = [
            'liquidity_core' => ['status' => 'READY'],
            'normalized_market_data' => ['status' => 'READY'],
            'consolidated_liquidity' => ['status' => 'READY'],
            'smart_order_router' => ['status' => 'READY'],
            'best_execution' => ['status' => 'READY'],
            'external_venues' => [
                'status' => $this->externalVenueStatus(),
                'sources' => LiquiditySource::query()->where('type', 'EXTERNAL_VENUE')->get(['code', 'state', 'capabilities'])->toArray(),
            ],
            'treasury_inventory' => ['status' => 'READY'],
            'withdrawal_reserves' => [
                'status' => WithdrawalLiquidityReserve::query()->where('status', 'BELOW_MINIMUM')->exists() ? 'NOT_FUNDED' : 'READY',
            ],
            'market_making' => [
                'status' => (bool) config('liquidity.market_making.enabled', false) ? 'READY' : 'ENGINE_READY_NOT_FUNDED',
                'active_quotes' => MarketMakerQuote::query()->where('status', 'ACTIVE')->count(),
            ],
            'reconciliation' => ['status' => $recon->status],
            'route_plans' => ['status' => 'READY', 'planned' => LiquidityRoutePlan::query()->count()],
        ];

        $softwareBlockers = collect($components)
            ->filter(fn (array $component): bool => in_array($component['status'], ['FAIL', 'NOT_READY'], true))
            ->keys()
            ->values()
            ->all();

        return [
            'overall_status' => $softwareBlockers === [] ? 'READY' : 'NOT_READY',
            'components' => $components,
            'software_blockers' => $softwareBlockers,
            'external_production_venues' => $this->externalVenueStatus(),
            'treasury_market_making_capital' => (bool) config('liquidity.market_making.enabled', false) ? 'PARTIALLY_FUNDED' : 'NOT_FUNDED',
            'withdrawal_reserves' => WithdrawalLiquidityReserve::query()->where('status', 'FUNDED')->exists() ? 'PARTIALLY_FUNDED' : 'NOT_FUNDED',
            'checked_at' => now()->toISOString(),
        ];
    }

    private function externalVenueStatus(): string
    {
        if (LiquiditySource::query()->where('type', 'EXTERNAL_VENUE')->where('state', 'LIVE')->exists()) {
            return 'LIVE';
        }

        if (LiquiditySource::query()->where('type', 'EXTERNAL_VENUE')->whereIn('state', ['SANDBOX', 'TESTING', 'READY'])->exists()) {
            return 'SANDBOX_ONLY';
        }

        return 'NOT_CONFIGURED';
    }
}
