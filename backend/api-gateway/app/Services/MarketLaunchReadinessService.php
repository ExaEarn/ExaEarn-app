<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\ListingApplication;
use App\Models\ListingLiquidityRequirement;
use App\Models\ListingMarketConfiguration;
use App\Models\ListingTestRun;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;

class MarketLaunchReadinessService
{
    public function __construct(
        private readonly MarketMakerProgramService $marketMakers,
        private readonly CompliancePolicyService $compliance,
    )
    {
    }

    public function evaluate(ListingApplication $application): array
    {
        $blockers = [];
        $warnings = [];
        $markets = $application->marketConfigurations()->get();

        if ($application->application_status !== 'APPROVED') {
            $blockers[] = 'LISTING_NOT_APPROVED';
        }
        if (! $application->assetConfiguration) {
            $blockers[] = 'ASSET_NOT_CONFIGURED';
        }
        if ($markets->isEmpty()) {
            $blockers[] = 'MARKET_NOT_CONFIGURED';
        }
        if (ListingTestRun::query()->where('application_id', $application->id)->latest()->value('overall_status') !== 'PASS') {
            $blockers[] = 'LISTING_TESTS_NOT_PASSING';
        }

        $marketResults = [];
        foreach ($markets as $configuration) {
            $marketResults[] = $this->evaluateMarket($application, $configuration, $blockers, $warnings);
        }

        return [
            'application_reference' => $application->reference,
            'status' => $blockers === [] ? 'READY' : 'BLOCKED',
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'markets' => $marketResults,
            'no_unsafe_auto_launch' => true,
        ];
    }

    private function evaluateMarket(ListingApplication $application, ListingMarketConfiguration $configuration, array &$blockers, array &$warnings): array
    {
        $symbol = strtoupper((string) $configuration->symbol);
        $market = $configuration->market_id ? Market::query()->find($configuration->market_id) : null;
        $requirement = ListingLiquidityRequirement::query()->where('listing_market_configuration_id', $configuration->id)->first();
        $assignments = MarketMakerMarketAssignment::query()->where('market_symbol', $symbol)->where('status', 'ACTIVE')->get();
        $profiles = MarketMakerProfile::query()->whereIn('id', $assignments->pluck('market_maker_id'))->where('status', 'ACTIVE')->get();
        $capital = $profiles->map(fn (MarketMakerProfile $profile): array => $this->marketMakers->capitalReadiness($profile, $symbol))->all();
        $capitalReady = collect($capital)->contains(fn (array $row): bool => $row['status'] === 'READY');
        $botReady = MarketMakerBot::query()
            ->where('market_symbol', $symbol)
            ->whereIn('status', ['APPROVED', 'ACTIVE'])
            ->whereIn('safety_state', ['NORMAL', null])
            ->where(function ($query): void {
                $query->whereNull('api_key_id')
                    ->orWhereIn('api_key_id', DeveloperApiKey::query()->where('status', 'ACTIVE')->select('id'));
            })
            ->exists();

        if (! $market || $market->status !== 'pre_launch' || $configuration->status !== 'PRE_LAUNCH') {
            $blockers[] = 'MARKET_NOT_PRE_LAUNCH';
        }
        if (! $requirement) {
            $blockers[] = 'LIQUIDITY_REQUIREMENT_MISSING';
        }
        if ($assignments->isEmpty()) {
            $blockers[] = 'MM_ASSIGNMENT_MISSING';
        }
        if (! $capitalReady) {
            $blockers[] = 'MM_CAPITAL_NOT_READY';
        }
        if (! $botReady) {
            $warnings[] = 'MM_BOT_NOT_READY';
        }
        $policy = $this->compliance->decide(null, 'TOKEN_LISTING', [
            'action' => 'LAUNCH',
            'market_symbol' => $symbol,
            'asset' => strtoupper((string) ($application->asset_symbol ?? $configuration->base_asset ?? explode('/', $symbol)[0] ?? '')),
            'log' => false,
        ]);
        if (! in_array($policy['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
            $blockers[] = 'COMPLIANCE_POLICY_NOT_READY';
        }

        return [
            'symbol' => $symbol,
            'market_state' => $market?->status,
            'trading_status' => $market?->trading_status,
            'liquidity_requirement' => $requirement?->toArray(),
            'active_assignments' => $assignments->count(),
            'active_market_makers' => $profiles->count(),
            'capital_ready' => $capitalReady,
            'bot_ready' => $botReady,
            'compliance' => $policy,
            'capital' => $capital,
        ];
    }
}
