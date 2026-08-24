<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use App\Models\MarginBadDebt;
use App\Models\MarginLoan;
use Illuminate\Support\Str;

class NegativeEquityProtectionService
{
    public function __construct(
        private readonly AccountEquityService $equity,
        private readonly TradingIncidentService $incidents,
    ) {
    }

    public function checkUser(int $userId): array
    {
        $equity = $this->equity->userEquity($userId);
        if ($equity['status'] !== 'NEGATIVE_EQUITY') {
            return ['status' => 'PASS', 'equity' => $equity];
        }

        $incident = $this->incidents->open('NEGATIVE_EQUITY', 'CRITICAL', 'User account equity is below zero.', 'USER', (string) $userId, $equity);

        foreach (MarginAccount::query()->where('user_id', $userId)->get() as $account) {
            foreach (MarginLoan::query()->where('margin_account_id', $account->id)->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID])->get() as $loan) {
                MarginBadDebt::query()->firstOrCreate([
                    'margin_account_id' => $account->id,
                    'asset' => $loan->asset,
                    'status' => 'OPEN',
                ], [
                    'bad_debt_id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'amount' => FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest),
                    'covered_amount' => '0',
                    'metadata' => ['incident_id' => $incident->incident_id],
                ]);
            }
        }

        return ['status' => 'CRITICAL', 'incident_id' => $incident->incident_id, 'equity' => $equity];
    }
}
