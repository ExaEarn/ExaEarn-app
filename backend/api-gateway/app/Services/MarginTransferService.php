<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;

class MarginTransferService
{
    public function __construct(
        private readonly MarginAccountService $accounts,
        private readonly MarginHealthService $health,
        private readonly SettlementService $settlement,
        private readonly MarginRealtimeService $realtime,
    ) {
    }

    public function transferInto(MarginAccount $account, string $fromAccountType, string $asset, string $amount, string $reference): void
    {
        $this->settlement->marginTransfer(
            (int) $account->user_id,
            $fromAccountType,
            $this->accounts->ledgerAccountType($account),
            strtoupper($asset),
            FinancialDecimal::normalize($amount),
            $reference,
            ['margin_account_id' => $account->id, 'direction' => 'into_margin'],
        );

        $this->realtime->publishAccount($account, 'margin.account.transferred_in', [
            'asset' => strtoupper($asset),
            'amount' => FinancialDecimal::normalize($amount),
            'from_account_type' => $fromAccountType,
        ]);
    }

    public function transferOut(MarginAccount $account, string $toAccountType, string $asset, string $amount, string $reference): void
    {
        $amount = FinancialDecimal::normalize($amount);
        $this->health->assertTransferOutAllowed($account, strtoupper($asset), $amount);
        $this->settlement->marginTransfer(
            (int) $account->user_id,
            $this->accounts->ledgerAccountType($account),
            $toAccountType,
            strtoupper($asset),
            $amount,
            $reference,
            ['margin_account_id' => $account->id, 'direction' => 'out_of_margin'],
        );

        $this->realtime->publishAccount($account, 'margin.account.transferred_out', [
            'asset' => strtoupper($asset),
            'amount' => $amount,
            'to_account_type' => $toAccountType,
        ]);
    }
}
