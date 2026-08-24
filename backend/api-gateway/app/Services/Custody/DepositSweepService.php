<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepositSweepService
{
    public function evaluate(string $asset, string $network, string $addressBalance, ?string $sourceWalletId = null): array
    {
        $assetConfig = DB::table('blockchain_assets')->where('asset', strtoupper($asset))->where('network', strtolower($network))->first();
        $threshold = (string) ($assetConfig?->sweep_threshold ?? '0');
        $action = FinancialDecimal::compare($addressBalance, $threshold) >= 0 && FinancialDecimal::compare($addressBalance, '0') > 0
            ? 'SWEEP_TO_HOT'
            : 'CONSOLIDATE_LATER';

        if (FinancialDecimal::compare($addressBalance, (string) config('custody.limits.dust_threshold', '0.00000001')) <= 0) {
            $action = 'NO_ACTION';
        }

        $sweepId = (string) Str::uuid();
        DB::table('custody_sweeps')->insert([
            'sweep_id' => $sweepId,
            'network' => strtolower($network),
            'asset' => strtoupper($asset),
            'from_custody_wallet_id' => $sourceWalletId,
            'amount' => FinancialDecimal::normalize($addressBalance),
            'action' => $action,
            'status' => $action === 'NO_ACTION' ? 'SKIPPED' : 'PLANNED',
            'metadata' => json_encode(['threshold' => $threshold], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('custody_sweeps')->where('sweep_id', $sweepId)->first();
    }
}
