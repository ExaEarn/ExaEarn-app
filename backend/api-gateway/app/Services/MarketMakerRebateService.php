<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerRebatePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketMakerRebateService
{
    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly LedgerService $ledger,
    ) {
    }

    public function accrue(MarketMakerProfile $profile, ?MarketMakerMarketAssignment $assignment, string $periodStart, string $periodEnd, string $volume, string $rebateBps, string $asset): MarketMakerRebatePeriod
    {
        $amount = FinancialDecimal::div(FinancialDecimal::mul(FinancialDecimal::normalize($volume), FinancialDecimal::normalize($rebateBps, 8)), '10000');

        return MarketMakerRebatePeriod::query()->firstOrCreate(
            [
                'market_maker_id' => $profile->id,
                'assignment_id' => $assignment?->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            [
                'rebate_uuid' => (string) Str::uuid(),
                'period_type' => 'DAILY',
                'eligible_maker_volume' => FinancialDecimal::normalize($volume),
                'disqualified_volume' => '0',
                'rebate_asset' => strtoupper($asset),
                'rebate_amount' => $amount,
                'status' => 'ACCRUED',
                'metadata' => ['rebate_bps' => $rebateBps],
            ]
        );
    }

    public function pay(Admin $admin, MarketMakerRebatePeriod $period, string $reason): MarketMakerRebatePeriod
    {
        return DB::transaction(function () use ($admin, $period, $reason): MarketMakerRebatePeriod {
            $period = MarketMakerRebatePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->status === 'PAID') {
                return $period;
            }

            $profile = MarketMakerProfile::query()->findOrFail($period->market_maker_id);
            $destination = $this->institutions->canonicalSubaccountLedgerAccount($profile->subaccount_id, $period->rebate_asset);
            $rebatePool = $this->ledger->getOrCreateAccount(null, 'market_maker_rebate_pool', $period->rebate_asset);
            $reference = 'MM-REBATE-'.$period->rebate_uuid;
            $tx = $this->ledger->postDoubleEntry($reference, 'Market maker rebate settlement', [
                ['account_id' => $rebatePool->id, 'amount' => FinancialDecimal::sub('0', (string) $period->rebate_amount), 'asset' => $period->rebate_asset, 'metadata' => ['rebate_period_id' => $period->id]],
                ['account_id' => $destination->id, 'amount' => (string) $period->rebate_amount, 'asset' => $period->rebate_asset, 'metadata' => ['rebate_period_id' => $period->id]],
            ], 'market_maker_rebate', [
                'source_service' => 'market_maker_rebate',
                'initiated_by_type' => 'admin',
                'initiated_by_id' => $admin->id,
                'reason' => $reason,
            ]);
            $period->forceFill(['status' => 'PAID', 'settlement_reference' => $tx->reference])->save();

            return $period->fresh();
        });
    }
}
