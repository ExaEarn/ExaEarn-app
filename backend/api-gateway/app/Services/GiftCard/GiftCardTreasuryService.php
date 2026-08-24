<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Services\BalanceProjectionService;
use App\Services\LedgerService;

class GiftCardTreasuryService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly GiftCardProviderManager $providers,
    ) {
    }

    public function accounts(string $asset): array
    {
        $asset = strtoupper($asset);

        return [
            'provider_settlement' => $this->ledger->getOrCreateAccount(null, 'giftcard_provider_settlement', $asset),
            'payout_treasury' => $this->ledger->getOrCreateAccount(null, 'giftcard_payout_treasury', $asset),
            'fee_revenue' => $this->ledger->getOrCreateAccount(null, 'giftcard_fee_revenue', $asset),
            'refund_liability' => $this->ledger->getOrCreateAccount(null, 'giftcard_refund_liability', $asset),
        ];
    }

    public function overview(string $asset): array
    {
        $accounts = $this->accounts($asset);
        $providerBalance = $this->providers->provider()->balance($asset);

        return [
            'asset' => strtoupper($asset),
            'provider_balance' => $providerBalance,
            'accounts' => collect($accounts)->map(fn ($account) => $this->balances->accountProjection($account))->all(),
            'real_provider_connected' => $this->providers->productionReady(),
        ];
    }
}

