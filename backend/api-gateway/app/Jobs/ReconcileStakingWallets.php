<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileStakingWallets implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public function handle(): void
    {
        foreach (DB::table('staking_assets')->get() as $asset) {
            $activePrincipal = DB::table('staking_positions')
                ->where('staking_asset_id', $asset->id)
                ->sum('active_principal_amount');
            $delegated = DB::table('staking_delegations')
                ->where('staking_asset_id', $asset->id)
                ->sum('active_amount');

            $difference = $this->sub((string) $delegated, (string) $activePrincipal);
            $reportId = DB::table('staking_reconciliation_reports')->insertGetId([
                'staking_asset_id' => $asset->id,
                'status' => $this->compare($difference, '0') !== 0 ? 'difference_detected' : 'balanced',
                'amount' => $difference,
                'reference' => (string) Str::uuid(),
                'metadata' => json_encode([
                    'active_user_principal' => (string) $activePrincipal,
                    'active_delegated_amount' => (string) $delegated,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($this->compare($difference, '0') !== 0) {
                DB::table('staking_reconciliation_differences')->insert([
                    'staking_asset_id' => $asset->id,
                    'subject_type' => 'staking_reconciliation_report',
                    'subject_id' => $reportId,
                    'status' => 'open',
                    'amount' => $difference,
                    'reference' => (string) Str::uuid(),
                    'metadata' => json_encode(['auto_pause_recommended' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:reconcile-wallets';
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 18) : number_format((float) $a - (float) $b, 18, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 18);
        }

        return (float) $a <=> (float) $b;
    }
}
