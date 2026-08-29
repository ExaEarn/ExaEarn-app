<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\AgriInvestorAllocation;
use App\Models\Farmer;
use App\Models\FarmInvestment;
use App\Models\FarmLease;

class AgriAccountClosureService
{
    public function blockers(int $userId): array
    {
        $blockers = [];
        if (FarmInvestment::query()->where('user_id', $userId)->whereIn('financial_status', ['RESERVING', 'RESERVED', 'SETTLED_IN_ESCROW', 'REFUND_PENDING'])->exists()) {
            $blockers[] = ['product' => 'AGRITECH', 'code' => 'ACTIVE_INVESTMENT_OBLIGATION'];
        }
        if (AgriInvestorAllocation::query()->where('user_id', $userId)->whereIn('status', ['PAYABLE', 'PROCESSING', 'FAILED_RETRYABLE'])->exists()) {
            $blockers[] = ['product' => 'AGRITECH', 'code' => 'PENDING_INVESTOR_PAYOUT'];
        }
        $farmerId = Farmer::query()->where('user_id', $userId)->value('id');
        if ($farmerId && FarmLease::query()->where('farmer_id', $farmerId)->whereIn('status', ['pending', 'active', 'SETTLEMENT_PENDING'])->exists()) {
            $blockers[] = ['product' => 'AGRITECH', 'code' => 'ACTIVE_FARM_OR_LEASE_OBLIGATION'];
        }

        return $blockers;
    }
}
