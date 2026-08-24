<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use App\Models\InstitutionalAccount;
use App\Models\MarketMakerBot;
use App\Models\OtcTrade;

class InstitutionalRiskOverviewService
{
    public function __construct(
        private readonly BalanceProjectionService $balances,
        private readonly MarketMakerCrossProductRiskService $crossRisk,
    ) {
    }

    public function overview(?InstitutionalAccount $institution = null): array
    {
        $institutions = $institution ? collect([$institution]) : InstitutionalAccount::query()->where('status', 'ACTIVE')->get();
        $rows = $institutions->map(function (InstitutionalAccount $account): array {
            $subaccounts = $account->subaccounts()->get();
            $bots = MarketMakerBot::query()->where('institution_id', $account->id)->get();
            $botRisk = $bots->map(fn (MarketMakerBot $bot): array => $this->crossRisk->exposure($bot))->all();
            return [
                'institution_id' => $account->id,
                'status' => $account->status,
                'subaccounts' => $subaccounts->count(),
                'market_maker_bots' => $bots->count(),
                'otc_open_trades' => OtcTrade::query()->where('institution_id', $account->id)->whereNotIn('status', ['SETTLED', 'FAILED'])->count(),
                'futures_positions' => FuturesPosition::query()->where('user_id', $account->master_user_id)->where('status', 'open')->count(),
                'cross_product' => $botRisk,
            ];
        })->all();

        return [
            'status' => 'READY',
            'institutions' => $rows,
            'treasury_boundaries' => ['CUSTOMER_FUNDS', 'INSTITUTIONAL_FUNDS', 'MM_FUNDS', 'PROJECT_LIQUIDITY', 'EXAEARN_TREASURY'],
        ];
    }
}
