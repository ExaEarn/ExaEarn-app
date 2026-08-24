<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ListingApplication;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\OtcSettlement;
use App\Models\OtcTrade;
use App\Models\Phase15ReconciliationDifference;
use App\Models\Phase15ReconciliationRun;
use App\Models\Reservation;
use Illuminate\Support\Str;

class Phase15ReconciliationService
{
    public function run(string $scope = 'GLOBAL'): Phase15ReconciliationRun
    {
        $run = Phase15ReconciliationRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'scope' => strtoupper($scope),
            'status' => 'RUNNING',
            'summary' => [],
            'started_at' => now(),
        ]);

        $this->listingChecks($run);
        $this->marketMakerChecks($run);
        $this->botChecks($run);
        $this->otcChecks($run);
        $this->capitalReservationChecks($run);

        $count = Phase15ReconciliationDifference::query()->where('run_id', $run->id)->count();
        $critical = Phase15ReconciliationDifference::query()->where('run_id', $run->id)->where('severity', 'CRITICAL')->count();
        $run->forceFill([
            'status' => $critical > 0 ? 'CRITICAL_BREAKS_FOUND' : ($count > 0 ? 'BREAKS_FOUND' : 'PASS'),
            'difference_count' => $count,
            'summary' => ['critical' => $critical, 'differences' => $count],
            'finished_at' => now(),
        ])->save();

        return $run->fresh();
    }

    private function listingChecks(Phase15ReconciliationRun $run): void
    {
        ListingApplication::query()->where('integration_status', 'LIVE')->get()->each(function (ListingApplication $application) use ($run): void {
            if ($application->marketConfigurations()->where('status', 'LIVE')->count() < 1) {
                $this->difference($run, 'LISTING', 'CRITICAL', 'LIVE_LISTING_WITHOUT_LIVE_MARKET', 'listing_application', (string) $application->id, ['reference' => $application->reference]);
            }
        });
        Market::query()->whereIn('status', ['active', 'trading'])->get()->each(function (Market $market) use ($run): void {
            if (! \App\Models\ListingMarketConfiguration::query()->where('market_id', $market->id)->whereIn('status', ['LIVE', 'PRE_LAUNCH'])->exists() && ($market->metadata['phase15_listed'] ?? false)) {
                $this->difference($run, 'LISTING', 'HIGH', 'MARKET_ACTIVE_WITHOUT_LISTING_CONFIGURATION', 'market', (string) $market->id, ['symbol' => $market->symbol]);
            }
        });
    }

    private function marketMakerChecks(Phase15ReconciliationRun $run): void
    {
        MarketMakerMarketAssignment::query()->where('status', 'ACTIVE')->get()->each(function (MarketMakerMarketAssignment $assignment) use ($run): void {
            $profile = MarketMakerProfile::query()->find($assignment->market_maker_id);
            if (! $profile || $profile->status !== 'ACTIVE') {
                $this->difference($run, 'MARKET_MAKER', 'CRITICAL', 'ACTIVE_ASSIGNMENT_WITHOUT_ACTIVE_MM', 'market_maker_assignment', (string) $assignment->id, ['market_symbol' => $assignment->market_symbol]);
            }
        });
    }

    private function botChecks(Phase15ReconciliationRun $run): void
    {
        MarketMakerBot::query()->whereIn('status', ['APPROVED', 'ACTIVE', 'LIMIT_NEW_RISK'])->get()->each(function (MarketMakerBot $bot) use ($run): void {
            $profile = MarketMakerProfile::query()->find($bot->market_maker_id);
            if (! $profile || $profile->status !== 'ACTIVE') {
                $this->difference($run, 'MM_BOT', 'CRITICAL', 'ACTIVE_BOT_WITHOUT_ACTIVE_MM', 'market_maker_bot', (string) $bot->id, ['bot_uuid' => $bot->bot_uuid]);
            }
            if (! MarketMakerMarketAssignment::query()->where('market_maker_id', $bot->market_maker_id)->where('market_symbol', $bot->market_symbol)->where('status', 'ACTIVE')->exists()) {
                $this->difference($run, 'MM_BOT', 'CRITICAL', 'ACTIVE_BOT_WITHOUT_MARKET_ASSIGNMENT', 'market_maker_bot', (string) $bot->id, ['market_symbol' => $bot->market_symbol]);
            }
        });
    }

    private function otcChecks(Phase15ReconciliationRun $run): void
    {
        $settledWithoutLedger = OtcTrade::query()->where('status', 'SETTLED')->whereNull('ledger_reference')->count()
            + OtcSettlement::query()->where('status', 'SETTLED')->whereNull('ledger_reference')->count();
        if ($settledWithoutLedger > 0) {
            $this->difference($run, 'OTC', 'CRITICAL', 'OTC_SETTLED_WITHOUT_LEDGER', null, null, ['count' => $settledWithoutLedger]);
        }
    }

    private function capitalReservationChecks(Phase15ReconciliationRun $run): void
    {
        $expiredActive = Reservation::query()->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])->whereNotNull('expires_at')->where('expires_at', '<', now())->count();
        if ($expiredActive > 0) {
            $this->difference($run, 'CAPITAL', 'HIGH', 'EXPIRED_ACTIVE_RESERVATION', null, null, ['count' => $expiredActive]);
        }
    }

    private function difference(Phase15ReconciliationRun $run, string $module, string $severity, string $code, ?string $subjectType, ?string $subjectId, array $evidence): void
    {
        Phase15ReconciliationDifference::query()->create([
            'difference_uuid' => (string) Str::uuid(),
            'run_id' => $run->id,
            'module' => $module,
            'severity' => $severity,
            'code' => $code,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'evidence' => $evidence,
            'status' => 'OPEN',
        ]);
    }
}
