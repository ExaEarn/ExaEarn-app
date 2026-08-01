<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class LedgerService
{
    private const SCALE = 18;

    public function createTransaction(string $reference, string $description): LedgerTransaction
    {
        if ($reference === '') {
            throw new RuntimeException('Ledger transaction reference is required.');
        }

        return LedgerTransaction::query()->firstOrCreate(
            ['reference' => $reference],
            ['description' => $description, 'status' => 'pending']
        );
    }

    public function addEntry(
        int $accountId,
        string $amount,
        string $asset,
        string $reference,
        string $type,
        ?int $userId = null,
        array $metadata = []
    ): LedgerEntry {
        if ($reference === '') {
            throw new RuntimeException('Ledger entry reference is required.');
        }

        return DB::transaction(function () use ($accountId, $amount, $asset, $reference, $type, $userId, $metadata): LedgerEntry {
            $account = Account::query()->lockForUpdate()->findOrFail($accountId);
            if (strtoupper($account->asset) !== strtoupper($asset)) {
                throw new RuntimeException('Ledger entry asset mismatch with account.');
            }

            $newBalance = $this->add((string) $account->balance, $amount);
            if ($account->user_id !== null && $this->compare($newBalance, '0') < 0) {
                throw new RuntimeException('Balance cannot be negative.');
            }

            $account->balance = $newBalance;
            $account->save();

            return LedgerEntry::query()->create([
                'account_id' => $account->id,
                'user_id' => $userId ?? $account->user_id,
                'asset' => strtoupper($asset),
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference' => $reference,
                'transaction_type' => $type,
                'metadata' => $metadata,
            ]);
        });
    }

    public function commitTransaction(string $reference): LedgerTransaction
    {
        return DB::transaction(function () use ($reference): LedgerTransaction {
            $transaction = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            $entries = LedgerEntry::query()->where('reference', $reference)->lockForUpdate()->get();

            if ($entries->isEmpty()) {
                throw new RuntimeException('Cannot commit transaction without entries.');
            }

            $sum = '0';
            foreach ($entries as $entry) {
                $sum = $this->add($sum, (string) $entry->amount);
            }

            if ($this->compare($sum, '0') !== 0) {
                throw new RuntimeException('Double-entry check failed: sum(entries) must equal zero.');
            }

            $transaction->status = 'completed';
            $transaction->save();

            $this->publishLedgerUpdates($entries);

            return $transaction;
        });
    }

    public function rollbackTransaction(string $reference): LedgerTransaction
    {
        return DB::transaction(function () use ($reference): LedgerTransaction {
            $transaction = LedgerTransaction::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            $entries = LedgerEntry::query()->where('reference', $reference)->lockForUpdate()->get();

            foreach ($entries->reverse() as $entry) {
                $account = Account::query()->lockForUpdate()->findOrFail($entry->account_id);
                $rollbackAmount = $this->sub('0', (string) $entry->amount);
                $newBalance = $this->add((string) $account->balance, $rollbackAmount);
                if ($account->user_id !== null && $this->compare($newBalance, '0') < 0) {
                    throw new RuntimeException('Rollback would create negative balance.');
                }
                $account->balance = $newBalance;
                $account->save();
            }

            LedgerEntry::query()->where('reference', $reference)->delete();
            $transaction->status = 'failed';
            $transaction->save();

            return $transaction;
        });
    }

    public function postDoubleEntry(string $reference, string $description, array $entries, string $type, array $metadata = []): LedgerTransaction
    {
        return DB::transaction(function () use ($reference, $description, $entries, $type, $metadata): LedgerTransaction {
            $tx = $this->createTransaction($reference, $description);
            if ($tx->status === 'completed') {
                return $tx;
            }

            if (LedgerEntry::query()->where('reference', $reference)->exists()) {
                throw new RuntimeException('Duplicate ledger reference detected.');
            }

            foreach ($entries as $entry) {
                $this->addEntry(
                    (int) $entry['account_id'],
                    (string) $entry['amount'],
                    (string) $entry['asset'],
                    $reference,
                    $type,
                    $entry['user_id'] ?? null,
                    array_merge($metadata, $entry['metadata'] ?? [])
                );
            }

            return $this->commitTransaction($reference);
        });
    }

    public function getOrCreateAccount(?int $userId, string $accountType, string $asset): Account
    {
        $asset = strtoupper($asset);

        $defaults = ['balance' => '0'];

        if ($userId !== null && $accountType === 'funding') {
            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->where('currency', $asset)
                ->first();

            if ($wallet) {
                $defaults['balance'] = (string) $wallet->available_balance;
            }
        }

        return Account::query()->firstOrCreate([
            'user_id' => $userId,
            'account_type' => $accountType,
            'asset' => $asset,
        ], $defaults);
    }

    public function getBalance(int $userId, string $asset, string $accountType = 'funding'): string
    {
        $account = Account::query()
            ->where('user_id', $userId)
            ->where('account_type', $accountType)
            ->where('asset', strtoupper($asset))
            ->first();

        return $account ? (string) $account->balance : '0';
    }

    public function hasBalance(int $userId, string $amount, string $asset, string $accountType = 'funding'): bool
    {
        return $this->compare($this->getBalance($userId, $asset, $accountType), $amount) >= 0;
    }

    public function getEntries(int $userId, string $asset, ?int $limit = null): Collection
    {
        $query = LedgerEntry::query()->where('user_id', $userId)->where('asset', strtoupper($asset))->latest();
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(function (LedgerEntry $entry) {
            $entry->setAttribute('type', $this->compare((string) $entry->amount, '0') >= 0 ? 'credit' : 'debit');

            return $entry;
        });
    }

    public function credit(int $userId, string $amount, string $asset, string $reference, ?string $description = null): LedgerEntry
    {
        $userFunding = $this->getOrCreateAccount($userId, 'funding', $asset);
        $treasury = $this->getOrCreateAccount(null, 'treasury', $asset);

        $this->postDoubleEntry(
            $reference,
            $description ?? 'User credit',
            [
                ['account_id' => $treasury->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset],
                ['account_id' => $userFunding->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId],
            ],
            'reward'
        );

        return LedgerEntry::query()->where('reference', $reference)->where('account_id', $userFunding->id)->firstOrFail();
    }

    public function debit(int $userId, string $amount, string $asset, string $reference, ?string $description = null): LedgerEntry
    {
        $userFunding = $this->getOrCreateAccount($userId, 'funding', $asset);
        $treasury = $this->getOrCreateAccount(null, 'treasury', $asset);

        $this->postDoubleEntry(
            $reference,
            $description ?? 'User debit',
            [
                ['account_id' => $userFunding->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'user_id' => $userId],
                ['account_id' => $treasury->id, 'amount' => $amount, 'asset' => $asset],
            ],
            'withdrawal'
        );

        return LedgerEntry::query()->where('reference', $reference)->where('account_id', $userFunding->id)->firstOrFail();
    }

    public function fiatDeposit(int $userId, string $amount, string $asset, string $reference): LedgerTransaction
    {
        $treasury = $this->getOrCreateAccount(null, 'treasury', $asset);
        $funding = $this->getOrCreateAccount($userId, 'funding', $asset);

        return $this->postDoubleEntry($reference, 'Fiat deposit', [
            ['account_id' => $treasury->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset],
            ['account_id' => $funding->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId],
        ], 'deposit');
    }

    public function cryptoDeposit(int $userId, string $amount, string $asset, string $reference): LedgerTransaction
    {
        return $this->fiatDeposit($userId, $amount, $asset, $reference);
    }

    public function internalTransfer(int $userId, string $fromType, string $toType, string $amount, string $asset, string $reference): LedgerTransaction
    {
        $from = $this->getOrCreateAccount($userId, $fromType, $asset);
        $to = $this->getOrCreateAccount($userId, $toType, $asset);

        return $this->postDoubleEntry($reference, 'Internal transfer', [
            ['account_id' => $from->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'user_id' => $userId],
            ['account_id' => $to->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $userId],
        ], 'transfer');
    }

    public function withdrawal(int $userId, string $amount, string $asset, string $reference): LedgerTransaction
    {
        $funding = $this->getOrCreateAccount($userId, 'funding', $asset);
        $treasury = $this->getOrCreateAccount(null, 'treasury', $asset);

        return $this->postDoubleEntry($reference, 'Withdrawal', [
            ['account_id' => $funding->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'user_id' => $userId],
            ['account_id' => $treasury->id, 'amount' => $amount, 'asset' => $asset],
        ], 'withdrawal');
    }

    public function chargeFee(int $userId, string $amount, string $asset, string $reference): LedgerTransaction
    {
        $userFunding = $this->getOrCreateAccount($userId, 'funding', $asset);
        $feeAccount = $this->getOrCreateAccount(null, 'system_treasury', $asset);

        return $this->postDoubleEntry($reference, 'Fee charge', [
            ['account_id' => $userFunding->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'user_id' => $userId],
            ['account_id' => $feeAccount->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => ['treasury_user_id' => config('fees.treasury_user_id', 'system_treasury')]],
        ], 'fee');
    }

    public function exapointReward(int $userId, string $points, string $reference): LedgerTransaction
    {
        $pool = $this->getOrCreateAccount(null, 'exapoint', 'EXAPOINT');
        $userExapoint = $this->getOrCreateAccount($userId, 'exapoint', 'EXAPOINT');

        return $this->postDoubleEntry($reference, 'ExaPoint reward', [
            ['account_id' => $pool->id, 'amount' => $this->sub('0', $points), 'asset' => 'EXAPOINT'],
            ['account_id' => $userExapoint->id, 'amount' => $points, 'asset' => 'EXAPOINT', 'user_id' => $userId],
        ], 'reward');
    }

    public function trade(array $legs, string $reference): LedgerTransaction
    {
        return $this->postDoubleEntry($reference, 'Trade execution', $legs, 'trade');
    }

    /**
     * Record giftcard purchase with complete fee accounting.
     * User wallet → Treasury | Card cost + user fees
     */
    public function giftcardPurchase(int $userId, float $cardValue, float $userChargedFees, string $asset, string $reference, int $giftcardOrderId): LedgerTransaction
    {
        $userFunding = $this->getOrCreateAccount($userId, 'funding', strtoupper($asset));
        $treasury = $this->getOrCreateAccount(null, 'treasury', strtoupper($asset));

        $totalAmount = $this->add((string) $cardValue, (string) $userChargedFees);

        return $this->postDoubleEntry($reference, "Gift card purchase #{$giftcardOrderId}", [
            ['account_id' => $userFunding->id, 'amount' => $this->sub('0', $totalAmount), 'asset' => $asset, 'user_id' => $userId, 'metadata' => ['giftcard_order_id' => $giftcardOrderId]],
            ['account_id' => $treasury->id, 'amount' => $totalAmount, 'asset' => $asset, 'metadata' => ['giftcard_order_id' => $giftcardOrderId]],
        ], 'giftcard_purchase');
    }

    /**
     * Record API fee deduction from treasury to external provider.
     */
    public function giftcardApiFeeDeduction(float $apiFee, float $deliveryFee, string $asset, string $reference, string $provider = 'external'): LedgerTransaction
    {
        $treasury = $this->getOrCreateAccount(null, 'treasury', strtoupper($asset));
        $externalProvider = $this->getOrCreateAccount(null, 'external_provider', strtoupper($asset));

        $totalFee = $this->add((string) $apiFee, (string) $deliveryFee);

        return $this->postDoubleEntry($reference, "API fee to {$provider}", [
            ['account_id' => $treasury->id, 'amount' => $this->sub('0', $totalFee), 'asset' => $asset, 'metadata' => ['provider' => $provider, 'api_fee' => $apiFee, 'delivery_fee' => $deliveryFee]],
            ['account_id' => $externalProvider->id, 'amount' => $totalFee, 'asset' => $asset],
        ], 'api_fee');
    }

    /**
     * Record platform profit to profit reserve account.
     */
    public function recordPlatformProfit(float $profit, string $asset, string $reference, int $giftcardOrderId, string $reason = 'fee_markup'): LedgerTransaction
    {
        $treasury = $this->getOrCreateAccount(null, 'treasury', strtoupper($asset));
        $profitReserve = $this->getOrCreateAccount(null, 'profit_reserve', strtoupper($asset));

        return $this->postDoubleEntry($reference, "Platform profit #{$giftcardOrderId}", [
            ['account_id' => $treasury->id, 'amount' => $this->sub('0', (string) $profit), 'asset' => $asset, 'metadata' => ['giftcard_order_id' => $giftcardOrderId, 'reason' => $reason]],
            ['account_id' => $profitReserve->id, 'amount' => (string) $profit, 'asset' => $asset],
        ], 'platform_profit');
    }

    /**
     * Record giftcard refund back to user.
     */
    public function giftcardRefund(int $userId, float $refundAmount, string $asset, string $reference, int $giftcardOrderId, string $reason = 'cancelled'): LedgerTransaction
    {
        $treasury = $this->getOrCreateAccount(null, 'treasury', strtoupper($asset));
        $userFunding = $this->getOrCreateAccount($userId, 'funding', strtoupper($asset));

        return $this->postDoubleEntry($reference, "Giftcard refund #{$giftcardOrderId}: {$reason}", [
            ['account_id' => $treasury->id, 'amount' => $this->sub('0', (string) $refundAmount), 'asset' => $asset, 'metadata' => ['giftcard_order_id' => $giftcardOrderId, 'reason' => $reason]],
            ['account_id' => $userFunding->id, 'amount' => (string) $refundAmount, 'asset' => $asset, 'user_id' => $userId],
        ], 'giftcard_refund');
    }

    /**
     * Get platform revenue summary for reporting.
     */
    public function getPlatformRevenueSummary(?string $asset = null, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $query = LedgerEntry::query()
            ->whereBetween('created_at', [$from, $to]);

        if ($asset) {
            $query->where('asset', strtoupper($asset));
        }

        $purchaseRows = (clone $query)
            ->where('transaction_type', 'giftcard_purchase')
            ->selectRaw('asset, COUNT(*) as count, SUM(ABS(amount)) as total')
            ->groupBy('asset')
            ->get();

        $profitRows = (clone $query)
            ->where('transaction_type', 'platform_profit')
            ->selectRaw('asset, SUM(amount) as total')
            ->groupBy('asset')
            ->get();

        $feeRows = (clone $query)
            ->where('transaction_type', 'api_fee')
            ->selectRaw('asset, SUM(amount) as total')
            ->groupBy('asset')
            ->get();

        $refundRows = (clone $query)
            ->where('transaction_type', 'giftcard_refund')
            ->selectRaw('asset, SUM(amount) as total')
            ->groupBy('asset')
            ->get();

        return [
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'summary' => [
                'total_purchases' => $purchaseRows->sum(fn ($row) => (float) $row->total),
                'total_profit' => $profitRows->sum(fn ($row) => (float) $row->total),
                'total_api_costs' => $feeRows->sum(fn ($row) => (float) $row->total),
                'total_refunds' => abs($refundRows->sum(fn ($row) => (float) $row->total)),
                'transaction_count' => $purchaseRows->sum(fn ($row) => (int) $row->count),
            ],
            'by_asset' => [
                'purchases' => $purchaseRows->pluck('total', 'asset'),
                'profits' => $profitRows->pluck('total', 'asset'),
                'api_costs' => $feeRows->pluck('total', 'asset'),
                'refunds' => $refundRows->pluck('total', 'asset'),
            ],
        ];
    }

    private function publishLedgerUpdates(Collection $entries): void
    {
        $grouped = $entries->groupBy('account_id');

        foreach ($grouped as $accountEntries) {
            $entry = $accountEntries->last();
            $account = Account::query()->find($entry->account_id);
            if (! $account) {
                continue;
            }

            try {
                Redis::publish('ledger_updates', json_encode([
                    'user_id' => $account->user_id,
                    'account_type' => $account->account_type,
                    'asset' => $account->asset,
                    'balance' => (string) $account->balance,
                ]));
            } catch (\Throwable $e) {
                // Ignore Redis publish failures in test and environments without Redis.
            }
        }
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format(((float) $a + (float) $b), self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format(((float) $a - (float) $b), self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }
        $fa = (float) $a;
        $fb = (float) $b;

        return $fa < $fb ? -1 : ($fa > $fb ? 1 : 0);
    }
}
