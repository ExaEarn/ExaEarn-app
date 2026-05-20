<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Log;

class ReconciliationService
{
    public function reconcileTreasuryAgainstLedger(): array
    {
        $assets = Account::query()->distinct()->pluck('asset')->toArray();
        $report = [];

        foreach ($assets as $asset) {
            $userTotal = (string) Account::query()
                ->whereNotNull('user_id')
                ->where('asset', $asset)
                ->sum('balance');

            $treasuryTotal = (string) Account::query()
                ->whereNull('user_id')
                ->where('account_type', 'treasury')
                ->where('asset', $asset)
                ->sum('balance');

            $matched = bccomp($userTotal, $treasuryTotal, 18) === 0;

            $report[$asset] = [
                'asset' => $asset,
                'user_total' => $userTotal,
                'treasury_total' => $treasuryTotal,
                'matched' => $matched,
            ];

            if (!$matched) {
                Log::warning('Ledger reconciliation mismatch', $report[$asset]);
            }
        }

        return $report;
    }
}
