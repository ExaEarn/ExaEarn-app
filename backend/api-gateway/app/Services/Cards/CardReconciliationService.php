<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Account;
use App\Models\CardProviderBalance;
use App\Models\CardReconciliationRun;
use Illuminate\Support\Str;

class CardReconciliationService
{
    public function __construct(private readonly CardOperationsAlertService $alerts)
    {
    }

    public function run(): CardReconciliationRun
    {
        $findings = [];
        $liabilities = Account::query()
            ->where('account_type', 'exacard')
            ->selectRaw('asset, SUM(balance) as total')
            ->groupBy('asset')
            ->get();

        foreach ($liabilities as $row) {
            $asset = strtoupper((string) $row->asset);
            $providerBalance = CardProviderBalance::query()->where('currency', $asset)->sum('available');
            $findings[] = [
                'currency' => $asset,
                'ledger_card_liability' => (string) $row->total,
                'provider_available' => (string) ($providerBalance ?: '0'),
                'status' => $providerBalance ? 'OBSERVED' : 'PROVIDER_BALANCE_MISSING',
            ];
        }

        $failed = collect($findings)->contains(fn (array $finding): bool => $finding['status'] !== 'OBSERVED');
        foreach ($findings as $finding) {
            if ($finding['status'] !== 'OBSERVED') {
                $this->alerts->reconciliationBreak($finding);
            }
        }

        return CardReconciliationRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'status' => $failed ? 'REVIEW_REQUIRED' : 'PASS',
            'summary' => [
                'currencies_checked' => count($findings),
                'provider_balances_missing' => collect($findings)->where('status', 'PROVIDER_BALANCE_MISSING')->count(),
            ],
            'findings' => $findings,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
