<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\InternalAccount;
use App\Models\InternalWalletTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnifiedTradingReservationService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly UnifiedTradingAccountService $accounts,
    ) {
    }

    public function reserveSpotOrder(
        int $userId,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = [],
    ): void {
        $this->transactions->recordLockedOperation(
            $userId,
            TransactionType::Trade,
            strtoupper($currency),
            $this->fmt($amount),
            $reference,
            $metadata,
        );
    }

    public function releaseSpotOrder(
        int $userId,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = [],
    ): void {
        $this->transactions->releaseLockedFunds(
            $userId,
            TransactionType::Trade,
            strtoupper($currency),
            $this->fmt($amount),
            $reference,
            $metadata,
        );
    }

    public function getUnifiedMarginStatus(int $userId): array
    {
        $balance = $this->accounts->getUnifiedTradingBalances($userId)['USDT'] ?? [
            'available' => '0',
            'locked' => '0',
            'total' => '0',
        ];

        $total = $this->fmt((string) ($balance['total'] ?? '0'));
        $available = $this->fmt((string) ($balance['transferable'] ?? $balance['available'] ?? '0'));
        $locked = $this->fmt((string) ($balance['in_use'] ?? $balance['locked'] ?? '0'));

        return [
            'total_margin' => $total,
            'available_margin' => $available,
            'locked_margin' => $locked,
            'margin_usage_percentage' => $this->compare($total, '0') > 0
                ? $this->mul($this->div($locked, $total), '100')
                : '0',
        ];
    }

    public function validateFuturesMargin(int $userId, string $requiredMargin): void
    {
        $required = $this->fmt($requiredMargin);
        $status = $this->getUnifiedMarginStatus($userId);

        if ($this->compare((string) $status['available_margin'], $required) < 0) {
            throw new RuntimeException('Insufficient unified trading margin balance.');
        }
    }

    /**
     * @return array<int, array{bucket:string, amount:string}>
     */
    public function reserveFuturesMargin(int $userId, string $amount, string $reference): array
    {
        $required = $this->fmt($amount);
        $this->validateFuturesMargin($userId, $required);

        return DB::transaction(function () use ($reference, $required, $userId): array {
            $wallet = $this->lockTradingWallet($userId, 'USDT');
            $futures = $this->lockFuturesAccount($userId);

            $remaining = $required;
            $allocations = [];

            $fromTradingWallet = $this->min((string) $wallet->available_balance, $remaining);
            if ($this->compare($fromTradingWallet, '0') > 0) {
                $wallet->available_balance = $this->sub((string) $wallet->available_balance, $fromTradingWallet);
                $futures->locked_balance = $this->add((string) $futures->locked_balance, $fromTradingWallet);
                $remaining = $this->sub($remaining, $fromTradingWallet);
                $allocations[] = ['bucket' => 'spot_available', 'amount' => $fromTradingWallet];
            }

            if ($this->compare($remaining, '0') > 0) {
                if ($this->compare((string) $futures->available_balance, $remaining) < 0) {
                    throw new RuntimeException('Insufficient unified trading margin balance.');
                }

                $futures->available_balance = $this->sub((string) $futures->available_balance, $remaining);
                $futures->locked_balance = $this->add((string) $futures->locked_balance, $remaining);
                $allocations[] = ['bucket' => 'futures_available', 'amount' => $remaining];
                $remaining = '0';
            }

            $wallet->save();
            $futures->save();

            $this->recordInternalWalletTx(
                $userId,
                'lock',
                'unified_trading',
                'USDT',
                $required,
                $reference,
                'Lock unified trading margin for futures order'
            );

            return $allocations;
        });
    }

    /**
     * @param  array<int, array{bucket:string, amount:string}>|null  $allocations
     */
    public function releaseFuturesMargin(int $userId, string $amount, string $reference, ?array $allocations = null): void
    {
        $releaseAmount = $this->fmt($amount);

        DB::transaction(function () use ($allocations, $reference, $releaseAmount, $userId): void {
            $wallet = $this->lockTradingWallet($userId, 'USDT');
            $futures = $this->lockFuturesAccount($userId);

            if ($this->compare((string) $futures->locked_balance, $releaseAmount) < 0) {
                throw new RuntimeException('Insufficient locked futures margin.');
            }

            $remaining = $releaseAmount;
            $moves = is_array($allocations) && $allocations !== []
                ? $allocations
                : [['bucket' => 'futures_available', 'amount' => $releaseAmount]];

            foreach ($moves as $move) {
                $bucket = (string) ($move['bucket'] ?? 'futures_available');
                $chunk = $this->fmt((string) ($move['amount'] ?? '0'));
                if ($this->compare($chunk, '0') <= 0) {
                    continue;
                }

                $applied = $this->min($chunk, $remaining);
                if ($this->compare($applied, '0') <= 0) {
                    continue;
                }

                $futures->locked_balance = $this->sub((string) $futures->locked_balance, $applied);
                if ($bucket === 'spot_available') {
                    $wallet->available_balance = $this->add((string) $wallet->available_balance, $applied);
                } else {
                    $futures->available_balance = $this->add((string) $futures->available_balance, $applied);
                }

                $remaining = $this->sub($remaining, $applied);
            }

            if ($this->compare($remaining, '0') > 0) {
                throw new RuntimeException('Unable to release full futures margin allocation.');
            }

            $wallet->save();
            $futures->save();

            $this->recordInternalWalletTx(
                $userId,
                'release',
                'unified_trading',
                'USDT',
                $releaseAmount,
                $reference,
                'Release unified trading margin from futures order'
            );
        });
    }

    private function lockTradingWallet(int $userId, string $asset): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $userId, 'currency' => strtoupper($asset)],
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

    private function recordInternalWalletTx(
        int $userId,
        string $type,
        string $walletType,
        string $asset,
        string $amount,
        string $reference,
        string $description,
    ): void {
        InternalWalletTransaction::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'wallet_type' => $walletType,
            'asset' => strtoupper($asset),
            'amount' => $this->fmt($amount),
            'reference' => $reference,
            'description' => $description,
        ]);
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

    private function mul(string $left, string $right): string
    {
        return bcmul($left, $right, 8);
    }

    private function div(string $left, string $right): string
    {
        if (bccomp($right, '0', 8) === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return bcdiv($left, $right, 8);
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
