<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccountTransfer;
use App\Models\InternalAccount;
use App\Models\InternalWalletTransaction;
use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UnifiedTradingAccountService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    public function getAccountsOverview(int $userId): array
    {
        $funding = $this->getFundingBalances($userId);
        $unified = $this->getUnifiedTradingBalances($userId);

        return [
            'accounts' => [
                [
                    'key' => 'funding',
                    'label' => 'Funding Account',
                    'description' => 'Deposits, withdrawals, receive and payment rails.',
                    'asset_count' => count($funding),
                ],
                [
                    'key' => 'unified_trading',
                    'label' => 'Unified Trading Account',
                    'description' => 'Shared collateral for Spot, Futures, Perpetuals and future margin products.',
                    'asset_count' => count($unified),
                ],
            ],
            'assets' => $this->buildCombinedAssetRows($funding, $unified),
        ];
    }

    public function getFundingSummary(int $userId): array
    {
        return [
            'account' => [
                'key' => 'funding',
                'label' => 'Funding Account',
            ],
            'balances' => array_values($this->getFundingBalances($userId)),
        ];
    }

    public function getUnifiedTradingSummary(int $userId): array
    {
        return [
            'account' => [
                'key' => 'unified_trading',
                'label' => 'Unified Trading Account',
            ],
            'balances' => array_values($this->getUnifiedTradingBalances($userId)),
        ];
    }

    public function getUnifiedTradingBalances(int $userId): array
    {
        $balances = [];

        Wallet::query()
            ->where('user_id', $userId)
            ->orderBy('currency')
            ->get()
            ->each(function (Wallet $wallet) use (&$balances): void {
                $asset = strtoupper((string) $wallet->currency);
                $available = $this->fmt((string) $wallet->available_balance);
                $locked = $this->fmt((string) $wallet->locked_balance);

                $balances[$asset] = [
                    'asset' => $asset,
                    'available' => $available,
                    'locked' => $locked,
                    'total' => $this->add($available, $locked),
                    'transferable' => $available,
                    'spot_available' => $available,
                    'spot_locked' => $locked,
                    'futures_available' => '0',
                    'futures_margin' => '0',
                    'unrealized_pnl' => '0',
                    'in_use' => $locked,
                ];
            });

        $futuresAccount = InternalAccount::query()
            ->where('user_id', $userId)
            ->where('account_type', 'futures_wallet')
            ->first();

        if ($futuresAccount) {
            $asset = 'USDT';
            $existing = $balances[$asset] ?? [
                'asset' => $asset,
                'available' => '0',
                'locked' => '0',
                'total' => '0',
                'transferable' => '0',
                'spot_available' => '0',
                'spot_locked' => '0',
                'futures_available' => '0',
                'futures_margin' => '0',
                'unrealized_pnl' => '0',
                'in_use' => '0',
            ];

            $futuresAvailable = $this->fmt((string) $futuresAccount->available_balance);
            $futuresMargin = $this->fmt((string) $futuresAccount->locked_balance);

            $existing['available'] = $this->add($existing['available'], $futuresAvailable);
            $existing['locked'] = $this->add($existing['locked'], $futuresMargin);
            $existing['total'] = $this->add($existing['available'], $existing['locked']);
            $existing['transferable'] = $this->add($existing['transferable'], $futuresAvailable);
            $existing['futures_available'] = $futuresAvailable;
            $existing['futures_margin'] = $futuresMargin;
            $existing['in_use'] = $this->add($existing['spot_locked'], $futuresMargin);

            $balances[$asset] = $existing;
        }

        ksort($balances);

        return $balances;
    }

    public function getTransfers(int $userId, int $limit = 50): Collection
    {
        return AccountTransfer::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function transfer(
        int $userId,
        string $fromAccount,
        string $toAccount,
        string $asset,
        string $amount,
        ?string $idempotencyKey = null,
    ): AccountTransfer {
        $from = strtolower(trim($fromAccount));
        $to = strtolower(trim($toAccount));
        $normalizedAsset = strtoupper(trim($asset));
        $normalizedAmount = $this->fmt($amount);

        if (!in_array($from, ['funding', 'unified_trading'], true) || !in_array($to, ['funding', 'unified_trading'], true)) {
            throw new RuntimeException('Transfers are only supported between Funding and Unified Trading.');
        }

        if ($from === $to) {
            throw new RuntimeException('Cannot transfer to the same account.');
        }

        if ($this->compare($normalizedAmount, '0') <= 0) {
            throw new RuntimeException('Transfer amount must be greater than zero.');
        }

        if ($idempotencyKey) {
            $existing = AccountTransfer::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($from, $idempotencyKey, $normalizedAmount, $normalizedAsset, $to, $userId): AccountTransfer {
            $reference = 'EXA-TR-' . strtoupper(Str::random(10));

            if ($from === 'unified_trading') {
                $this->seedUnifiedTradingLedgerBalanceIfNeeded($userId, $normalizedAsset);
            }

            if ($from === 'funding') {
                $movement = $this->moveFundingToUnified($userId, $normalizedAsset, $normalizedAmount);
            } else {
                $movement = $this->moveUnifiedToFunding($userId, $normalizedAsset, $normalizedAmount);
            }

            $transfer = AccountTransfer::query()->create([
                'user_id' => $userId,
                'reference' => $reference,
                'from_account' => $from,
                'to_account' => $to,
                'asset' => $normalizedAsset,
                'amount' => $normalizedAmount,
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'metadata' => $movement,
                'completed_at' => now(),
            ]);

            InternalWalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => 'transfer_out',
                'wallet_type' => $from,
                'asset' => $normalizedAsset,
                'amount' => $normalizedAmount,
                'reference' => $reference,
                'description' => 'Unified account transfer out',
            ]);

            InternalWalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => 'transfer_in',
                'wallet_type' => $to,
                'asset' => $normalizedAsset,
                'amount' => $normalizedAmount,
                'reference' => $reference,
                'description' => 'Unified account transfer in',
            ]);

            $this->ledgerService->internalTransfer(
                $userId,
                $from,
                $to,
                $normalizedAmount,
                $normalizedAsset,
                $reference,
            );

            return $transfer->fresh();
        });
    }

    private function seedUnifiedTradingLedgerBalanceIfNeeded(int $userId, string $asset): void
    {
        $ledgerAccount = $this->ledgerService->getOrCreateAccount($userId, 'unified_trading', $asset);
        $ledgerBalance = $this->fmt((string) $ledgerAccount->balance);
        $liveBalance = $this->calculateLiveUnifiedLedgerBalance($userId, $asset);

        if ($this->compare($liveBalance, $ledgerBalance) <= 0) {
            return;
        }

        $delta = $this->sub($liveBalance, $ledgerBalance);
        $reference = sprintf('EXA-UTMIG-%d-%s-%s', $userId, strtoupper($asset), strtoupper(Str::random(6)));
        $migrationAccount = $this->ledgerService->getOrCreateAccount(null, 'legacy_trading_migration', $asset);

        $this->ledgerService->createTransaction($reference, 'Seed unified trading ledger balance from legacy live balances');
        $this->ledgerService->addEntry(
            $migrationAccount->id,
            $this->sub('0', $delta),
            $asset,
            $reference,
            'migration',
            null,
            ['source' => 'unified_trading_seed'],
        );
        $this->ledgerService->addEntry(
            $ledgerAccount->id,
            $delta,
            $asset,
            $reference,
            'migration',
            $userId,
            ['source' => 'unified_trading_seed'],
        );
        $this->ledgerService->commitTransaction($reference);
    }

    private function calculateLiveUnifiedLedgerBalance(int $userId, string $asset): string
    {
        $total = '0';

        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('currency', $asset)
            ->first();

        if ($wallet) {
            $total = $this->add($total, $this->fmt((string) $wallet->available_balance));
            $total = $this->add($total, $this->fmt((string) $wallet->locked_balance));
        }

        if ($asset === 'USDT') {
            $futures = InternalAccount::query()
                ->where('user_id', $userId)
                ->where('account_type', 'futures_wallet')
                ->first();

            if ($futures) {
                $total = $this->add($total, $this->fmt((string) $futures->available_balance));
                $total = $this->add($total, $this->fmt((string) $futures->locked_balance));
            }
        }

        return $total;
    }

    private function getFundingBalances(int $userId): array
    {
        $balances = [];

        WalletBalance::query()
            ->where('user_id', $userId)
            ->where('wallet_type', 'funding')
            ->orderBy('asset')
            ->get()
            ->each(function (WalletBalance $walletBalance) use (&$balances): void {
                $asset = strtoupper((string) $walletBalance->asset);
                $available = $this->fmt((string) $walletBalance->balance);
                $balances[$asset] = [
                    'asset' => $asset,
                    'available' => $available,
                    'locked' => '0',
                    'total' => $available,
                    'transferable' => $available,
                    'in_use' => '0',
                ];
            });

        return $balances;
    }

    private function buildCombinedAssetRows(array $funding, array $unified): array
    {
        $configuredAssets = collect((array) config('wallet.assets', []))
            ->map(fn (array $asset): string => strtoupper((string) ($asset['code'] ?? '')))
            ->filter(fn (string $asset): bool => $asset !== '')
            ->values()
            ->all();

        $supportedFiat = collect((array) config('swap.supported_fiat', []))
            ->map(fn (string $asset): string => strtoupper($asset))
            ->filter(fn (string $asset): bool => $asset !== '')
            ->values()
            ->all();

        $assets = array_values(array_unique(array_merge(array_keys($funding), array_keys($unified), $configuredAssets, $supportedFiat)));
        sort($assets);

        return array_map(function (string $asset) use ($funding, $unified): array {
            return [
                'asset' => $asset,
                'funding' => $funding[$asset] ?? [
                    'available' => '0',
                    'locked' => '0',
                    'total' => '0',
                    'transferable' => '0',
                    'in_use' => '0',
                ],
                'unified_trading' => $unified[$asset] ?? [
                    'available' => '0',
                    'locked' => '0',
                    'total' => '0',
                    'transferable' => '0',
                    'spot_available' => '0',
                    'spot_locked' => '0',
                    'futures_available' => '0',
                    'futures_margin' => '0',
                    'unrealized_pnl' => '0',
                    'in_use' => '0',
                ],
            ];
        }, $assets);
    }

    private function moveFundingToUnified(int $userId, string $asset, string $amount): array
    {
        $funding = $this->lockFundingBalance($userId, $asset);

        if ($this->compare((string) $funding->balance, $amount) < 0) {
            throw new RuntimeException('Insufficient Funding balance.');
        }

        $wallet = $this->lockTradingWallet($userId, $asset);

        $funding->balance = $this->sub((string) $funding->balance, $amount);
        $funding->save();

        $wallet->available_balance = $this->add((string) $wallet->available_balance, $amount);
        $wallet->save();

        return [
            'source' => ['type' => 'funding', 'asset' => $asset, 'amount' => $amount],
            'destination' => ['type' => 'unified_trading', 'asset' => $asset, 'amount' => $amount],
        ];
    }

    private function moveUnifiedToFunding(int $userId, string $asset, string $amount): array
    {
        $transferable = $this->getUnifiedTradingBalances($userId)[$asset]['transferable'] ?? '0';
        if ($this->compare($transferable, $amount) < 0) {
            throw new RuntimeException('Transfer amount exceeds Unified Trading transferable balance.');
        }

        $remaining = $amount;
        $movement = [
            'source' => ['type' => 'unified_trading', 'asset' => $asset, 'amount' => $amount, 'allocations' => []],
            'destination' => ['type' => 'funding', 'asset' => $asset, 'amount' => $amount],
        ];

        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('currency', $asset)
            ->lockForUpdate()
            ->first();

        if ($wallet && $this->compare((string) $wallet->available_balance, '0') > 0) {
            $fromSpot = $this->min((string) $wallet->available_balance, $remaining);
            if ($this->compare($fromSpot, '0') > 0) {
                $wallet->available_balance = $this->sub((string) $wallet->available_balance, $fromSpot);
                $wallet->save();
                $remaining = $this->sub($remaining, $fromSpot);
                $movement['source']['allocations'][] = ['bucket' => 'spot_available', 'amount' => $fromSpot];
            }
        }

        if ($this->compare($remaining, '0') > 0) {
            if ($asset !== 'USDT') {
                throw new RuntimeException('Unified Trading transferable balance is unavailable for this asset.');
            }

            $futures = $this->lockFuturesAccount($userId);
            if ($this->compare((string) $futures->available_balance, $remaining) < 0) {
                throw new RuntimeException('Insufficient Unified Trading transferable balance.');
            }

            $futures->available_balance = $this->sub((string) $futures->available_balance, $remaining);
            $futures->save();

            $movement['source']['allocations'][] = ['bucket' => 'futures_available', 'amount' => $remaining];
            $remaining = '0';
        }

        $funding = $this->lockFundingBalance($userId, $asset);
        $funding->balance = $this->add((string) $funding->balance, $amount);
        $funding->save();

        return $movement;
    }

    private function lockFundingBalance(int $userId, string $asset): WalletBalance
    {
        $record = WalletBalance::query()->firstOrCreate(
            ['user_id' => $userId, 'wallet_type' => 'funding', 'asset' => $asset],
            ['balance' => '0'],
        );

        return WalletBalance::query()
            ->whereKey($record->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockTradingWallet(int $userId, string $asset): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $userId, 'currency' => $asset],
            ['available_balance' => '0', 'locked_balance' => '0'],
        );

        return Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    private function lockFuturesAccount(int $userId): InternalAccount
    {
        $account = InternalAccount::query()->firstOrCreate(
            ['user_id' => $userId, 'account_type' => 'futures_wallet'],
            ['account_name' => 'Futures Wallet', 'available_balance' => '0', 'locked_balance' => '0'],
        );

        return InternalAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }

    private function fmt(string $value): string
    {
        return bcadd(trim($value), '0', 8);
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, 8);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, 8);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, 8);
    }

    private function min(string $left, string $right): string
    {
        return $this->compare($left, $right) <= 0 ? $left : $right;
    }
}
