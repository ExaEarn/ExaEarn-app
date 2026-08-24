<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\CardFundingRequest;
use App\Models\CardUnloadRequest;
use App\Services\FinanceAccountingService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use Illuminate\Support\Str;

class CardSettlementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly FinanceAccountingService $finance,
    ) {
    }

    public function settleFunding(CardFundingRequest $funding): void
    {
        if ($funding->ledger_reference) {
            return;
        }

        $asset = strtoupper((string) $funding->source_asset);
        $reference = 'CARD-FUND-'.$funding->funding_uuid;
        $userFunding = $this->ledger->getOrCreateAccount((int) $funding->user_id, 'funding', $asset);
        $cardAccount = $this->ledger->getOrCreateAccount((int) $funding->user_id, 'exacard', $asset);
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'exacard_fee_revenue', $asset);
        $providerPayable = $this->ledger->getOrCreateAccount(null, 'exacard_provider_payable', $asset);
        $fee = (string) $funding->fee_amount;
        $providerFee = (string) $funding->provider_fee;

        $entries = [
            ['account_id' => $userFunding->id, 'amount' => FinancialDecimal::sub('0', (string) $funding->total_debit), 'asset' => $asset, 'user_id' => $funding->user_id],
            ['account_id' => $cardAccount->id, 'amount' => (string) $funding->card_amount, 'asset' => $asset, 'user_id' => $funding->user_id],
        ];
        if (FinancialDecimal::compare($fee, '0') > 0) {
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $fee, 'asset' => $asset, 'user_id' => null];
        }
        if (FinancialDecimal::compare($providerFee, '0') > 0) {
            $entries[] = ['account_id' => $providerPayable->id, 'amount' => $providerFee, 'asset' => $asset, 'user_id' => null];
        }

        $tx = $this->ledger->postDoubleEntry($reference, 'ExaCard funding settlement', $entries, 'card_funding', [
            'source_service' => 'exacard',
            'funding_uuid' => $funding->funding_uuid,
            'card_id' => $funding->card_id,
            'reservation_id' => $funding->reservation_id,
        ]);
        if ($funding->reservation_id) {
            $this->reservations->consume((string) $funding->reservation_id, (string) $funding->total_debit, ['ledger_reference' => $reference]);
        }
        $this->finance->recordLedgerEvent($tx, 'CARD_FUNDED', ['asset' => $asset, 'amount' => (string) $funding->total_debit, 'source_service' => 'exacard']);

        $funding->forceFill([
            'ledger_reference' => $reference,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ])->save();
    }

    public function settleUnload(CardUnloadRequest $unload): void
    {
        if ($unload->ledger_reference) {
            return;
        }

        $asset = strtoupper((string) $unload->asset);
        $reference = 'CARD-UNLOAD-'.$unload->unload_uuid;
        $cardAccount = $this->ledger->getOrCreateAccount((int) $unload->user_id, 'exacard', $asset);
        $userFunding = $this->ledger->getOrCreateAccount((int) $unload->user_id, 'funding', $asset);
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'exacard_fee_revenue', $asset);

        $entries = [
            ['account_id' => $cardAccount->id, 'amount' => FinancialDecimal::sub('0', (string) $unload->amount), 'asset' => $asset, 'user_id' => $unload->user_id],
            ['account_id' => $userFunding->id, 'amount' => (string) $unload->net_amount, 'asset' => $asset, 'user_id' => $unload->user_id],
        ];
        if (FinancialDecimal::compare((string) $unload->fee_amount, '0') > 0) {
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => (string) $unload->fee_amount, 'asset' => $asset, 'user_id' => null];
        }

        $tx = $this->ledger->postDoubleEntry($reference, 'ExaCard unload settlement', $entries, 'card_unload', [
            'source_service' => 'exacard',
            'unload_uuid' => $unload->unload_uuid,
            'card_id' => $unload->card_id,
        ]);
        $this->finance->recordLedgerEvent($tx, 'CARD_UNLOADED', ['asset' => $asset, 'amount' => (string) $unload->amount, 'source_service' => 'exacard']);

        $unload->forceFill([
            'ledger_reference' => $reference,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ])->save();
    }
}
