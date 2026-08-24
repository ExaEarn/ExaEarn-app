<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\OtcMarketConfig;
use App\Models\Phase15EmergencyControl;
use Illuminate\Support\Str;

class Phase15EmergencyControlService
{
    public function __construct(private readonly MarketMakerBotService $bots)
    {
    }

    public function activate(Admin $admin, string $scope, ?string $reference, string $control, string $reason): Phase15EmergencyControl
    {
        $scope = strtoupper($scope);
        $control = strtoupper($control);
        $before = $this->snapshot($scope, $reference);
        $this->apply($scope, $reference, $control, $reason);
        $after = $this->snapshot($scope, $reference);

        return Phase15EmergencyControl::query()->create([
            'control_uuid' => (string) Str::uuid(),
            'admin_id' => $admin->id,
            'scope' => $scope,
            'scope_reference' => $reference,
            'control' => $control,
            'status' => 'ACTIVE',
            'previous_state' => $before,
            'new_state' => $after,
            'reason' => $reason,
            'activated_at' => now(),
        ]);
    }

    private function apply(string $scope, ?string $reference, string $control, string $reason): void
    {
        $botQuery = MarketMakerBot::query();
        if ($scope === 'MARKET' && $reference) {
            $botQuery->where('market_symbol', strtoupper($reference));
            Market::query()->where('symbol', strtoupper($reference))->update(['status' => 'halted', 'trading_status' => 'HALTED']);
            OtcMarketConfig::query()->where('symbol', strtoupper(str_replace('/', '', $reference)))->update(['enabled' => false]);
        }
        if ($scope === 'INSTITUTION' && $reference) {
            $botQuery->where('institution_id', (int) $reference);
        }
        if ($scope === 'BOT' && $reference) {
            $botQuery->where('bot_uuid', $reference);
        }
        $botQuery->get()->each(function (MarketMakerBot $bot) use ($control, $reason): void {
            if (in_array($control, ['GLOBAL_LIQUIDITY_EMERGENCY', 'PAUSE_NEW_RISK', 'MARKET_HALT'], true)) {
                $this->bots->massCancel($bot, $reason);
                $bot->forceFill(['status' => 'PAUSED', 'safety_state' => 'PAUSED'])->save();
            }
        });
    }

    private function snapshot(string $scope, ?string $reference): array
    {
        $botQuery = MarketMakerBot::query();
        if ($scope === 'MARKET' && $reference) {
            $botQuery->where('market_symbol', strtoupper($reference));
        }
        if ($scope === 'INSTITUTION' && $reference) {
            $botQuery->where('institution_id', (int) $reference);
        }
        if ($scope === 'BOT' && $reference) {
            $botQuery->where('bot_uuid', $reference);
        }

        return [
            'bots' => $botQuery->get(['bot_uuid', 'status', 'safety_state', 'market_symbol'])->toArray(),
            'scope' => $scope,
            'reference' => $reference,
        ];
    }
}
