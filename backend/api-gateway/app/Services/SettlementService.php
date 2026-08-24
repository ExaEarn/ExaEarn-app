<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettlementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
    ) {
    }

    public function internalTransfer(
        int $userId,
        string $fromAccount,
        string $toAccount,
        string $asset,
        string $amount,
        string $reference,
        array $metadata = [],
    ): LedgerTransaction {
        $from = $this->ledger->getOrCreateAccount($userId, $fromAccount, $asset);
        $reservation = $this->reservations->reserve(
            $from->id,
            $asset,
            $amount,
            'internal_transfer',
            'internal_transfer',
            $reference,
            'reserve:' . $reference,
            $metadata,
        );

        $tx = $this->ledger->internalTransfer($userId, $fromAccount, $toAccount, $amount, $asset, $reference);
        $this->reservations->consume((string) $reservation->reservation_id, $amount, ['ledger_reference' => $reference]);

        return $tx;
    }

    public function depositCredit(int $userId, string $accountType, string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        $treasury = $this->ledger->getOrCreateAccount(null, 'treasury', $asset);
        $userAccount = $this->ledger->getOrCreateAccount($userId, $accountType, $asset);

        return $this->ledger->postDoubleEntry($reference, 'Deposit credit', [
            ['account_id' => $treasury->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
            ['account_id' => $userAccount->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
        ], 'deposit', array_merge($metadata, ['source_service' => 'settlement']));
    }

    public function withdrawalDebit(string $reservationId, string $reference, string $destinationAccountType = 'external_withdrawal', array $metadata = []): LedgerTransaction
    {
        return DB::transaction(function () use ($destinationAccountType, $metadata, $reference, $reservationId): LedgerTransaction {
        $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
        if ($existing?->status === 'completed') {
            return $existing;
        }
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $amount = (string) $reservation->remaining_amount;
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Reservation has no remaining amount to settle.');
        }

        $source = $reservation->account;
        $external = $this->ledger->getOrCreateAccount(null, $destinationAccountType, (string) $reservation->asset);

        $tx = $this->ledger->postDoubleEntry($reference, 'Withdrawal debit', [
            ['account_id' => $source->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $reservation->asset, 'user_id' => $source->user_id, 'metadata' => $metadata],
            ['account_id' => $external->id, 'amount' => $amount, 'asset' => $reservation->asset, 'metadata' => $metadata],
        ], 'withdrawal', array_merge($metadata, ['reservation_id' => $reservationId, 'source_service' => 'settlement']));

        $this->reservations->consume($reservationId, $amount, ['ledger_reference' => $reference]);

        return $tx;
        });
    }

    public function custodyWithdrawal(
        string $reservationId,
        string $reference,
        string $amount,
        string $networkFee,
        string $platformFee,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($amount, $metadata, $networkFee, $platformFee, $reference, $reservationId): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $total = FinancialDecimal::add($amount, FinancialDecimal::add($networkFee, $platformFee));
            if (FinancialDecimal::compare($total, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Withdrawal settlement exceeds reservation amount.');
            }

            $source = $reservation->account;
            $asset = (string) $reservation->asset;
            $external = $this->ledger->getOrCreateAccount(null, 'custody_external_withdrawal', $asset);
            $networkFeeAccount = $this->ledger->getOrCreateAccount(null, 'custody_network_fee_expense', $asset);
            $platformFeeAccount = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $asset);

            $entries = [
                ['account_id' => $source->id, 'amount' => FinancialDecimal::sub('0', $total), 'asset' => $asset, 'user_id' => $source->user_id, 'metadata' => $metadata],
                ['account_id' => $external->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => $metadata],
            ];
            if (FinancialDecimal::compare($networkFee, '0') > 0) {
                $entries[] = ['account_id' => $networkFeeAccount->id, 'amount' => $networkFee, 'asset' => $asset, 'metadata' => $metadata];
            }
            if (FinancialDecimal::compare($platformFee, '0') > 0) {
                $entries[] = ['account_id' => $platformFeeAccount->id, 'amount' => $platformFee, 'asset' => $asset, 'metadata' => $metadata];
            }

            $tx = $this->ledger->postDoubleEntry($reference, 'Custody withdrawal settlement', $entries, 'custody_withdrawal', array_merge($metadata, [
                'reservation_id' => $reservationId,
                'source_service' => 'custody_withdrawal',
            ]));

            $this->reservations->consume($reservationId, $total, ['ledger_reference' => $reference]);

            return $tx;
        });
    }

    public function fiatDepositCredit(int $userId, string $asset, string $grossAmount, string $feeAmount, string $reference, array $metadata = []): LedgerTransaction
    {
        $asset = strtoupper($asset);
        $netAmount = FinancialDecimal::sub($grossAmount, $feeAmount);
        if (FinancialDecimal::compare($netAmount, '0') <= 0) {
            throw new RuntimeException('Fiat deposit net amount must be greater than zero.');
        }

        $providerAsset = $this->ledger->getOrCreateAccount(null, 'fiat_provider_asset', $asset);
        $userLiability = $this->ledger->getOrCreateAccount($userId, 'funding', $asset);
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $asset);

        $entries = [
            ['account_id' => $providerAsset->id, 'amount' => FinancialDecimal::sub('0', $grossAmount), 'asset' => $asset, 'metadata' => $metadata],
            ['account_id' => $userLiability->id, 'amount' => $netAmount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
        ];
        if (FinancialDecimal::compare($feeAmount, '0') > 0) {
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $asset, 'metadata' => $metadata];
        }

        return $this->ledger->postDoubleEntry($reference, 'Fiat deposit credit', $entries, 'fiat_deposit', array_merge($metadata, ['source_service' => 'fiat_settlement']));
    }

    public function fiatWithdrawalSettle(string $reservationId, string $reference, string $amount, string $feeAmount, array $metadata = []): LedgerTransaction
    {
        return DB::transaction(function () use ($amount, $feeAmount, $metadata, $reference, $reservationId): LedgerTransaction {
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $asset = strtoupper((string) $reservation->asset);
            $total = FinancialDecimal::add($amount, $feeAmount);
            if (FinancialDecimal::compare($total, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Fiat withdrawal settlement exceeds reservation amount.');
            }

            $provider = $this->ledger->getOrCreateAccount(null, 'fiat_provider_asset', $asset);
            $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $asset);
            $source = $reservation->account;
            $entries = [
                ['account_id' => $source->id, 'amount' => FinancialDecimal::sub('0', $total), 'asset' => $asset, 'user_id' => $source->user_id, 'metadata' => $metadata],
                ['account_id' => $provider->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => $metadata],
            ];
            if (FinancialDecimal::compare($feeAmount, '0') > 0) {
                $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $asset, 'metadata' => $metadata];
            }

            $tx = $this->ledger->postDoubleEntry($reference, 'Fiat withdrawal settlement', $entries, 'fiat_withdrawal', array_merge($metadata, ['source_service' => 'fiat_settlement']));
            $this->reservations->consume($reservationId, $total, ['ledger_reference' => $reference]);

            return $tx;
        });
    }

    public function exaearnPayTransfer(int $payerId, int $recipientId, string $asset, string $amount, string $feeAmount, string $reference, array $metadata = []): LedgerTransaction
    {
        $asset = strtoupper($asset);
        $total = FinancialDecimal::add($amount, $feeAmount);
        $payer = $this->ledger->getOrCreateAccount($payerId, 'funding', $asset);
        $recipient = $this->ledger->getOrCreateAccount($recipientId, 'funding', $asset);
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $asset);
        $entries = [
            ['account_id' => $payer->id, 'amount' => FinancialDecimal::sub('0', $total), 'asset' => $asset, 'user_id' => $payerId, 'metadata' => $metadata],
            ['account_id' => $recipient->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $recipientId, 'metadata' => $metadata],
        ];
        if (FinancialDecimal::compare($feeAmount, '0') > 0) {
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $asset, 'metadata' => $metadata];
        }

        return $this->ledger->postDoubleEntry($reference, 'ExaEarn Pay internal settlement', $entries, 'exaearn_pay', array_merge($metadata, ['source_service' => 'exaearn_pay']));
    }

    public function giftcardPurchaseSettle(
        string $reservationId,
        string $reference,
        string $providerCost,
        string $feeAmount,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($feeAmount, $metadata, $providerCost, $reference, $reservationId): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $asset = strtoupper((string) $reservation->asset);
            $providerCost = FinancialDecimal::normalize($providerCost);
            $feeAmount = FinancialDecimal::normalize($feeAmount);
            $total = FinancialDecimal::add($providerCost, $feeAmount);
            if (FinancialDecimal::compare($total, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Gift card settlement exceeds reservation amount.');
            }

            $source = $reservation->account;
            $providerSettlement = $this->ledger->getOrCreateAccount(null, 'giftcard_provider_settlement', $asset);
            $feeRevenue = $this->ledger->getOrCreateAccount(null, 'giftcard_fee_revenue', $asset);
            $entries = [
                ['account_id' => $source->id, 'amount' => FinancialDecimal::sub('0', $total), 'asset' => $asset, 'user_id' => $source->user_id, 'metadata' => $metadata],
                ['account_id' => $providerSettlement->id, 'amount' => $providerCost, 'asset' => $asset, 'metadata' => $metadata],
            ];
            if (FinancialDecimal::compare($feeAmount, '0') > 0) {
                $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $asset, 'metadata' => $metadata];
            }

            $tx = $this->ledger->postDoubleEntry($reference, 'Gift card purchase settlement', $entries, 'giftcard_purchase', array_merge($metadata, [
                'reservation_id' => $reservationId,
                'source_service' => 'giftcard_settlement',
            ]));

            $this->reservations->consume($reservationId, $total, ['ledger_reference' => $reference]);

            return $tx;
        });
    }

    public function giftcardSellPayout(int $userId, string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        $asset = strtoupper($asset);
        $amount = FinancialDecimal::normalize($amount);
        $treasury = $this->ledger->getOrCreateAccount(null, 'giftcard_payout_treasury', $asset);
        $user = $this->ledger->getOrCreateAccount($userId, 'funding', $asset);

        return $this->ledger->postDoubleEntry($reference, 'Gift card sell payout', [
            ['account_id' => $treasury->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
            ['account_id' => $user->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
        ], 'giftcard_sell_payout', array_merge($metadata, ['source_service' => 'giftcard_settlement']));
    }

    public function giftcardRefundCredit(int $userId, string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        $asset = strtoupper($asset);
        $amount = FinancialDecimal::normalize($amount);
        $refundLiability = $this->ledger->getOrCreateAccount(null, 'giftcard_refund_liability', $asset);
        $user = $this->ledger->getOrCreateAccount($userId, 'funding', $asset);

        return $this->ledger->postDoubleEntry($reference, 'Gift card refund credit', [
            ['account_id' => $refundLiability->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
            ['account_id' => $user->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
        ], 'giftcard_refund', array_merge($metadata, ['source_service' => 'giftcard_settlement']));
    }

    public function p2pEscrowRelease(
        string $reservationId,
        int $buyerId,
        string $amount,
        string $feeAmount,
        string $reference,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($amount, $buyerId, $feeAmount, $metadata, $reference, $reservationId): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $asset = strtoupper((string) $reservation->asset);
            $amount = FinancialDecimal::normalize($amount);
            $feeAmount = FinancialDecimal::normalize($feeAmount);
            $totalDebit = FinancialDecimal::add($amount, $feeAmount);

            if (FinancialDecimal::compare($totalDebit, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('P2P escrow release exceeds reserved amount.');
            }

            $sellerAccount = $reservation->account;
            $buyerAccount = $this->ledger->getOrCreateAccount($buyerId, 'funding', $asset);
            $entries = [
                ['account_id' => $sellerAccount->id, 'amount' => FinancialDecimal::sub('0', $totalDebit), 'asset' => $asset, 'user_id' => $sellerAccount->user_id, 'metadata' => $metadata],
                ['account_id' => $buyerAccount->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $buyerId, 'metadata' => $metadata],
            ];

            if (FinancialDecimal::compare($feeAmount, '0') > 0) {
                $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $asset);
                $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $asset, 'metadata' => $metadata];
            }

            $tx = $this->ledger->postDoubleEntry($reference, 'P2P escrow release', $entries, 'p2p_escrow_release', array_merge($metadata, [
                'reservation_id' => $reservationId,
                'source_service' => 'p2p_settlement',
            ]));

            $this->reservations->consume($reservationId, $totalDebit, ['ledger_reference' => $reference]);

            return $tx;
        });
    }

    public function convert(
        string $reservationId,
        int $userId,
        string $fromAsset,
        string $fromAmount,
        string $toAsset,
        string $toAmount,
        string $feeAsset,
        string $feeAmount,
        string $reference,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($feeAmount, $feeAsset, $fromAmount, $fromAsset, $metadata, $reference, $reservationId, $toAmount, $toAsset, $userId): LedgerTransaction {
        $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
        if ($existing?->status === 'completed') {
            return $existing;
        }
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $source = $reservation->account;
        $to = $this->ledger->getOrCreateAccount($userId, 'funding', $toAsset);
        $clearingFrom = $this->ledger->getOrCreateAccount(null, 'convert_clearing', $fromAsset);
        $clearingTo = $this->ledger->getOrCreateAccount(null, 'convert_clearing', $toAsset);

        $netClearingFrom = strtoupper($feeAsset) === strtoupper($fromAsset)
            ? FinancialDecimal::sub($fromAmount, $feeAmount)
            : $fromAmount;
        $entries = [
            ['account_id' => $source->id, 'amount' => FinancialDecimal::sub('0', $fromAmount), 'asset' => $fromAsset, 'user_id' => $userId, 'metadata' => $metadata],
            ['account_id' => $clearingFrom->id, 'amount' => $netClearingFrom, 'asset' => $fromAsset, 'metadata' => $metadata],
            ['account_id' => $clearingTo->id, 'amount' => FinancialDecimal::sub('0', $toAmount), 'asset' => $toAsset, 'metadata' => $metadata],
            ['account_id' => $to->id, 'amount' => $toAmount, 'asset' => $toAsset, 'user_id' => $userId, 'metadata' => $metadata],
        ];

        if (FinancialDecimal::compare($feeAmount, '0') > 0) {
            $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $feeAsset);
            if (strtoupper($feeAsset) !== strtoupper($fromAsset)) {
                $feeSource = $this->ledger->getOrCreateAccount($userId, 'funding', $feeAsset);
                $entries[] = ['account_id' => $feeSource->id, 'amount' => FinancialDecimal::sub('0', $feeAmount), 'asset' => $feeAsset, 'user_id' => $userId, 'metadata' => $metadata];
            }
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $feeAsset, 'metadata' => $metadata];
        }

        $tx = $this->ledger->postDoubleEntry($reference, 'Convert settlement', $entries, 'convert', array_merge($metadata, ['reservation_id' => $reservationId, 'source_service' => 'settlement']));
        $this->reservations->consume($reservationId, $fromAmount, ['ledger_reference' => $reference]);

        return $tx;
        });
    }

    public function spotTrade(array $payload, string $reference): LedgerTransaction
    {
        return DB::transaction(function () use ($payload, $reference): LedgerTransaction {
        $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
        if ($existing?->status === 'completed') {
            return $existing;
        }

        $buyer = (int) $payload['buyer_user_id'];
        $seller = (int) $payload['seller_user_id'];
        if ($buyer === $seller) {
            throw new RuntimeException('Self-trade settlement is prohibited.');
        }
        $base = strtoupper((string) $payload['base_asset']);
        $quote = strtoupper((string) $payload['quote_asset']);
        $baseAmount = (string) $payload['base_amount'];
        $quoteAmount = (string) $payload['quote_amount'];
        $buyerFee = (string) ($payload['buyer_fee'] ?? '0');
        $sellerFee = (string) ($payload['seller_fee'] ?? '0');
        $buyerAccountType = (string) ($payload['buyer_account_type'] ?? 'unified_trading');
        $sellerAccountType = (string) ($payload['seller_account_type'] ?? 'unified_trading');
        $metadata = (array) ($payload['metadata'] ?? []);

        $buyerQuote = $this->ledger->getOrCreateAccount($buyer, $buyerAccountType, $quote);
        $buyerBase = $this->ledger->getOrCreateAccount($buyer, $buyerAccountType, $base);
        $sellerBase = $this->ledger->getOrCreateAccount($seller, $sellerAccountType, $base);
        $sellerQuote = $this->ledger->getOrCreateAccount($seller, $sellerAccountType, $quote);
        $baseFees = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $base);
        $quoteFees = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $quote);

        $buyerNetBase = FinancialDecimal::sub($baseAmount, $buyerFee);
        $sellerNetQuote = FinancialDecimal::sub($quoteAmount, $sellerFee);

        $entries = [
            ['account_id' => $buyerQuote->id, 'amount' => FinancialDecimal::sub('0', $quoteAmount), 'asset' => $quote, 'user_id' => $buyer, 'metadata' => $metadata],
            ['account_id' => $sellerQuote->id, 'amount' => $sellerNetQuote, 'asset' => $quote, 'user_id' => $seller, 'metadata' => $metadata],
            ['account_id' => $quoteFees->id, 'amount' => $sellerFee, 'asset' => $quote, 'metadata' => $metadata],
            ['account_id' => $sellerBase->id, 'amount' => FinancialDecimal::sub('0', $baseAmount), 'asset' => $base, 'user_id' => $seller, 'metadata' => $metadata],
            ['account_id' => $buyerBase->id, 'amount' => $buyerNetBase, 'asset' => $base, 'user_id' => $buyer, 'metadata' => $metadata],
            ['account_id' => $baseFees->id, 'amount' => $buyerFee, 'asset' => $base, 'metadata' => $metadata],
        ];

        $tx = $this->ledger->postDoubleEntry($reference, 'Spot trade settlement', $entries, 'spot_trade', array_merge($metadata, ['source_service' => 'settlement']));

        foreach ((array) ($payload['consume_reservations'] ?? []) as $reservationId => $amount) {
            $this->reservations->consume((string) $reservationId, (string) $amount, ['ledger_reference' => $reference]);
        }

        return $tx;
        });
    }

    public function spotExternalFill(array $payload, string $reference): LedgerTransaction
    {
        return DB::transaction(function () use ($payload, $reference): LedgerTransaction {
        $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
        if ($existing?->status === 'completed') {
            return $existing;
        }

        $userId = (int) $payload['user_id'];
        $side = strtolower((string) $payload['side']);
        $base = strtoupper((string) $payload['base_asset']);
        $quote = strtoupper((string) $payload['quote_asset']);
        $baseAmount = (string) $payload['base_amount'];
        $quoteAmount = (string) $payload['quote_amount'];
        $feeAmount = (string) ($payload['fee_amount'] ?? '0');
        $feeAsset = strtoupper((string) ($payload['fee_asset'] ?? ($side === 'buy' ? $base : $quote)));
        $metadata = (array) ($payload['metadata'] ?? []);

        $userBase = $this->ledger->getOrCreateAccount($userId, 'unified_trading', $base);
        $userQuote = $this->ledger->getOrCreateAccount($userId, 'unified_trading', $quote);
        $externalBase = $this->ledger->getOrCreateAccount(null, 'external_spot_liquidity', $base);
        $externalQuote = $this->ledger->getOrCreateAccount(null, 'external_spot_liquidity', $quote);
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'fee_revenue', $feeAsset);

        $entries = $side === 'buy'
            ? [
                ['account_id' => $userQuote->id, 'amount' => FinancialDecimal::sub('0', $quoteAmount), 'asset' => $quote, 'user_id' => $userId, 'metadata' => $metadata],
                ['account_id' => $externalQuote->id, 'amount' => $quoteAmount, 'asset' => $quote, 'metadata' => $metadata],
                ['account_id' => $externalBase->id, 'amount' => FinancialDecimal::sub('0', $baseAmount), 'asset' => $base, 'metadata' => $metadata],
                ['account_id' => $userBase->id, 'amount' => FinancialDecimal::sub($baseAmount, $feeAsset === $base ? $feeAmount : '0'), 'asset' => $base, 'user_id' => $userId, 'metadata' => $metadata],
            ]
            : [
                ['account_id' => $userBase->id, 'amount' => FinancialDecimal::sub('0', $baseAmount), 'asset' => $base, 'user_id' => $userId, 'metadata' => $metadata],
                ['account_id' => $externalBase->id, 'amount' => $baseAmount, 'asset' => $base, 'metadata' => $metadata],
                ['account_id' => $externalQuote->id, 'amount' => FinancialDecimal::sub('0', $quoteAmount), 'asset' => $quote, 'metadata' => $metadata],
                ['account_id' => $userQuote->id, 'amount' => FinancialDecimal::sub($quoteAmount, $feeAsset === $quote ? $feeAmount : '0'), 'asset' => $quote, 'user_id' => $userId, 'metadata' => $metadata],
            ];

        if (FinancialDecimal::compare($feeAmount, '0') > 0) {
            if ($feeAsset !== $base && $feeAsset !== $quote) {
                $feeSource = $this->ledger->getOrCreateAccount($userId, 'unified_trading', $feeAsset);
                $entries[] = ['account_id' => $feeSource->id, 'amount' => FinancialDecimal::sub('0', $feeAmount), 'asset' => $feeAsset, 'user_id' => $userId, 'metadata' => $metadata];
            }
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => $feeAmount, 'asset' => $feeAsset, 'metadata' => $metadata];
        }

        return $this->ledger->postDoubleEntry($reference, 'Spot external liquidity fill settlement', $entries, 'spot_external_fill', array_merge($metadata, ['source_service' => 'settlement']));
        });
    }

    public function futuresFundingPayment(array $payload, string $reference): LedgerTransaction
    {
        return DB::transaction(function () use ($payload, $reference): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $userId = (int) $payload['user_id'];
            $asset = strtoupper((string) ($payload['asset'] ?? 'USDT'));
            $amount = (string) $payload['amount'];
            $direction = strtolower((string) $payload['direction']);
            $metadata = (array) ($payload['metadata'] ?? []);

            if (FinancialDecimal::compare($amount, '0') <= 0) {
                throw new RuntimeException('Funding payment amount must be greater than zero.');
            }

            $user = $this->ledger->getOrCreateAccount($userId, 'futures', $asset);
            $pool = $this->ledger->getOrCreateAccount(null, 'futures_funding_pool', $asset);
            $entries = $direction === 'pay'
                ? [
                    ['account_id' => $user->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
                    ['account_id' => $pool->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => $metadata],
                ]
                : [
                    ['account_id' => $pool->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
                    ['account_id' => $user->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
                ];

            return $this->ledger->postDoubleEntry($reference, 'Futures funding payment', $entries, 'futures_funding', array_merge($metadata, ['source_service' => 'settlement']));
        });
    }

    public function marginPoolFunding(string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        $treasury = $this->ledger->getOrCreateAccount(null, 'treasury', $asset);
        $pool = $this->ledger->getOrCreateAccount(null, 'margin_lending_pool', $asset);

        return $this->ledger->postDoubleEntry($reference, 'Margin lending pool funding', [
            ['account_id' => $treasury->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
            ['account_id' => $pool->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => $metadata],
        ], 'margin_pool_funding', array_merge($metadata, ['source_service' => 'settlement']));
    }

    public function marginBorrow(int $userId, string $marginAccountType, string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        return DB::transaction(function () use ($amount, $asset, $marginAccountType, $metadata, $reference, $userId): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $pool = $this->ledger->getOrCreateAccount(null, 'margin_lending_pool', $asset);
            $borrower = $this->ledger->getOrCreateAccount($userId, $marginAccountType, $asset);

            return $this->ledger->postDoubleEntry($reference, 'Margin borrow settlement', [
                ['account_id' => $pool->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => $metadata],
                ['account_id' => $borrower->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
            ], 'margin_borrow', array_merge($metadata, ['source_service' => 'settlement']));
        });
    }

    public function marginRepay(
        int $userId,
        string $marginAccountType,
        string $asset,
        string $principalAmount,
        string $interestAmount,
        string $reserveFactor,
        string $reference,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($asset, $interestAmount, $marginAccountType, $metadata, $principalAmount, $reference, $reserveFactor, $userId): LedgerTransaction {
            $existing = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }

            $principalAmount = FinancialDecimal::normalize($principalAmount);
            $interestAmount = FinancialDecimal::normalize($interestAmount);
            $total = FinancialDecimal::add($principalAmount, $interestAmount);
            $borrower = $this->ledger->getOrCreateAccount($userId, $marginAccountType, $asset);
            $pool = $this->ledger->getOrCreateAccount(null, 'margin_lending_pool', $asset);
            $reserve = $this->ledger->getOrCreateAccount(null, 'margin_reserve_fund', $asset);
            $revenue = $this->ledger->getOrCreateAccount(null, 'margin_interest_revenue', $asset);
            $reserveFactor = FinancialDecimal::max('0', FinancialDecimal::min('1', $reserveFactor));
            $reserveShare = FinancialDecimal::mul($interestAmount, $reserveFactor);
            $revenueShare = FinancialDecimal::sub($interestAmount, $reserveShare);

            $entries = [
                ['account_id' => $borrower->id, 'amount' => FinancialDecimal::sub('0', $total), 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
            ];

            if (FinancialDecimal::compare($principalAmount, '0') > 0) {
                $entries[] = ['account_id' => $pool->id, 'amount' => $principalAmount, 'asset' => $asset, 'metadata' => $metadata];
            }
            if (FinancialDecimal::compare($reserveShare, '0') > 0) {
                $entries[] = ['account_id' => $reserve->id, 'amount' => $reserveShare, 'asset' => $asset, 'metadata' => $metadata];
            }
            if (FinancialDecimal::compare($revenueShare, '0') > 0) {
                $entries[] = ['account_id' => $revenue->id, 'amount' => $revenueShare, 'asset' => $asset, 'metadata' => array_merge($metadata, [
                    'interest_revenue' => true,
                    'reserve_factor' => $reserveFactor,
                ])];
            }

            $sum = '0';
            foreach ($entries as $entry) {
                $sum = FinancialDecimal::add($sum, (string) $entry['amount']);
            }
            $dustAdjustment = FinancialDecimal::sub('0', $sum);
            if (FinancialDecimal::compare($dustAdjustment, '0') !== 0) {
                $lastRevenueIndex = null;
                foreach ($entries as $index => $entry) {
                    if ((int) $entry['account_id'] === (int) $revenue->id) {
                        $lastRevenueIndex = $index;
                    }
                }
                if ($lastRevenueIndex === null) {
                    $entries[] = [
                        'account_id' => $revenue->id,
                        'amount' => $dustAdjustment,
                        'asset' => $asset,
                        'metadata' => array_merge($metadata, ['residual_interest_revenue' => true]),
                    ];
                } else {
                    $entries[$lastRevenueIndex]['amount'] = FinancialDecimal::add((string) $entries[$lastRevenueIndex]['amount'], $dustAdjustment);
                    $entries[$lastRevenueIndex]['metadata'] = array_merge($entries[$lastRevenueIndex]['metadata'] ?? [], [
                        'residual_adjustment' => $dustAdjustment,
                    ]);
                }
            }

            return $this->ledger->postDoubleEntry($reference, 'Margin repayment settlement', $entries, 'margin_repay', array_merge($metadata, ['source_service' => 'settlement']));
        });
    }

    public function marginTransfer(int $userId, string $fromAccount, string $toAccount, string $asset, string $amount, string $reference, array $metadata = []): LedgerTransaction
    {
        $from = $this->ledger->getOrCreateAccount($userId, $fromAccount, $asset);
        $to = $this->ledger->getOrCreateAccount($userId, $toAccount, $asset);

        return $this->ledger->postDoubleEntry($reference, 'Margin account transfer', [
            ['account_id' => $from->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
            ['account_id' => $to->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId, 'metadata' => $metadata],
        ], 'margin_transfer', array_merge($metadata, ['source_service' => 'settlement']));
    }
}
