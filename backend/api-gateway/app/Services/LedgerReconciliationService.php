<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Reservation;
use App\Models\Wallet;
use App\Models\WalletBalance;

class LedgerReconciliationService
{
    public function __construct(private readonly BalanceProjectionService $projections)
    {
    }

    public function run(?int $userId = null): array
    {
        return [
            'balanced_transaction_failures' => $this->findUnbalancedTransactions($userId),
            'negative_user_accounts' => $this->findNegativeUserAccounts($userId),
            'reservation_integrity_failures' => $this->findReservationIntegrityFailures($userId),
            'legacy_projection_mismatches' => $this->findLegacyProjectionMismatches($userId),
            'duplicate_references' => $this->findDuplicateReferences(),
            'generated_at' => now()->toISOString(),
        ];
    }

    private function findUnbalancedTransactions(?int $userId): array
    {
        $failures = [];
        LedgerTransaction::query()
            ->when($userId, fn ($query) => $query->whereHas('entries', fn ($entryQuery) => $entryQuery->where('user_id', $userId)))
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (&$failures): void {
                foreach ($transactions as $transaction) {
                    $entries = LedgerEntry::query()->where('reference', $transaction->reference)->get()->groupBy('asset');
                    foreach ($entries as $asset => $assetEntries) {
                        $sum = '0';
                        foreach ($assetEntries as $entry) {
                            $sum = FinancialDecimal::add($sum, (string) $entry->amount);
                        }
                        if (FinancialDecimal::compare($sum, '0') !== 0) {
                            $failures[] = ['reference' => $transaction->reference, 'asset' => $asset, 'difference' => $sum];
                        }
                    }
                }
            });

        return $failures;
    }

    private function findNegativeUserAccounts(?int $userId): array
    {
        return Account::query()
            ->whereNotNull('user_id')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('balance', '<', 0)
            ->get(['id', 'user_id', 'account_type', 'asset', 'balance'])
            ->map(fn (Account $account): array => $account->toArray())
            ->all();
    }

    private function findLegacyProjectionMismatches(?int $userId): array
    {
        $mismatches = [];

        WalletBalance::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$mismatches): void {
                foreach ($rows as $row) {
                    $projection = $this->projections->byUserAccountAndAsset((int) $row->user_id, (string) $row->wallet_type, (string) $row->asset);
                    if (FinancialDecimal::compare((string) $row->balance, (string) $projection['total']) !== 0) {
                        $mismatches[] = [
                            'legacy_table' => 'wallet_balances',
                            'user_id' => $row->user_id,
                            'account_type' => $row->wallet_type,
                            'asset' => $row->asset,
                            'legacy_balance' => (string) $row->balance,
                            'ledger_balance' => $projection['total'],
                        ];
                    }
                }
            });

        Wallet::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$mismatches): void {
                foreach ($rows as $row) {
                    $projection = $this->projections->byUserAccountAndAsset((int) $row->user_id, 'unified_trading', (string) $row->currency);
                    $legacyTotal = FinancialDecimal::add((string) $row->available_balance, (string) $row->locked_balance, 8);
                    if (FinancialDecimal::compare($legacyTotal, (string) $projection['total'], 8) !== 0) {
                        $mismatches[] = [
                            'legacy_table' => 'wallets',
                            'user_id' => $row->user_id,
                            'account_type' => 'unified_trading',
                            'asset' => $row->currency,
                            'legacy_balance' => $legacyTotal,
                            'ledger_balance' => $projection['total'],
                        ];
                    }
                }
            });

        return $mismatches;
    }

    private function findReservationIntegrityFailures(?int $userId): array
    {
        $failures = [];

        Reservation::query()
            ->with('account')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById(200, function ($reservations) use (&$failures): void {
                foreach ($reservations as $reservation) {
                    $amount = (string) $reservation->amount;
                    $remaining = (string) $reservation->remaining_amount;

                    if (!$reservation->account) {
                        $failures[] = [
                            'reservation_id' => $reservation->reservation_id,
                            'reason' => 'missing_account',
                        ];
                        continue;
                    }

                    if (strtoupper((string) $reservation->asset) !== strtoupper((string) $reservation->account->asset)) {
                        $failures[] = [
                            'reservation_id' => $reservation->reservation_id,
                            'reason' => 'asset_mismatch',
                            'reservation_asset' => $reservation->asset,
                            'account_asset' => $reservation->account->asset,
                        ];
                    }

                    if (FinancialDecimal::compare($amount, '0') <= 0) {
                        $failures[] = [
                            'reservation_id' => $reservation->reservation_id,
                            'reason' => 'non_positive_amount',
                            'amount' => $amount,
                        ];
                    }

                    if (FinancialDecimal::compare($remaining, '0') < 0 || FinancialDecimal::compare($remaining, $amount) > 0) {
                        $failures[] = [
                            'reservation_id' => $reservation->reservation_id,
                            'reason' => 'remaining_out_of_bounds',
                            'amount' => $amount,
                            'remaining_amount' => $remaining,
                        ];
                    }

                    if (in_array($reservation->status, [Reservation::STATUS_CONSUMED, Reservation::STATUS_RELEASED], true)
                        && FinancialDecimal::compare($remaining, '0') !== 0) {
                        $failures[] = [
                            'reservation_id' => $reservation->reservation_id,
                            'reason' => 'closed_reservation_has_remaining_amount',
                            'status' => $reservation->status,
                            'remaining_amount' => $remaining,
                        ];
                    }

                    if (in_array($reservation->status, [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
                        $projection = $this->projections->accountProjection($reservation->account);
                        if (FinancialDecimal::compare($projection['reserved'], $projection['total']) > 0) {
                            $failures[] = [
                                'reservation_id' => $reservation->reservation_id,
                                'reason' => 'reserved_exceeds_account_total',
                                'account_id' => $reservation->account_id,
                                'total' => $projection['total'],
                                'reserved' => $projection['reserved'],
                            ];
                        }
                    }
                }
            });

        return $failures;
    }

    private function findDuplicateReferences(): array
    {
        return LedgerTransaction::query()
            ->selectRaw('reference, COUNT(*) as count')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row): array => ['reference' => $row->reference, 'count' => (int) $row->count])
            ->all();
    }
}
