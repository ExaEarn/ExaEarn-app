<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LedgerTransaction;
use App\Models\TreasuryBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FeeTreasuryService
{
    public const TREASURY_USER_ID = 'system_treasury';
    private const SCALE = 18;

    public function __construct(
        private readonly FeeCalculator $fees,
        private readonly LedgerService $ledger,
    ) {
    }

    public function treasuryUserId(): string
    {
        return (string) config('fees.treasury_user_id', self::TREASURY_USER_ID);
    }

    public function collectWithdrawal(int $userId, string $grossAmount, string $asset, string $reference, array $metadata = []): array
    {
        $quote = $this->fees->withdrawal($grossAmount, $asset);

        return DB::transaction(function () use ($userId, $asset, $reference, $metadata, $quote): array {
            $asset = strtoupper($asset);
            $funding = $this->ledger->getOrCreateAccount($userId, 'funding', $asset);
            $treasury = $this->ledger->getOrCreateAccount(null, 'system_treasury', $asset);
            $external = $this->ledger->getOrCreateAccount(null, 'external_hot_wallet', $asset);

            $tx = $this->ledger->postDoubleEntry(
                $reference,
                'Withdrawal with platform fee',
                [
                    ['account_id' => $funding->id, 'amount' => $this->neg($quote['gross_amount']), 'asset' => $asset, 'user_id' => $userId, 'metadata' => ['leg' => 'user_gross_debit']],
                    ['account_id' => $external->id, 'amount' => $quote['net_amount'], 'asset' => $asset, 'metadata' => ['leg' => 'external_wallet_net_payout']],
                    ['account_id' => $treasury->id, 'amount' => $quote['fee_amount'], 'asset' => $asset, 'metadata' => ['leg' => 'treasury_fee_credit', 'treasury_user_id' => $this->treasuryUserId()]],
                ],
                'withdrawal_fee',
                array_merge($metadata, $quote)
            );

            $this->creditTreasuryHotBalance($asset, $quote['fee_amount']);
            $this->logCollection($userId, 'fee.withdrawal_collected', $reference, $quote, $metadata);

            return ['ledger_transaction' => $tx, 'fee' => $quote, 'net_payout' => $quote['net_amount']];
        });
    }

    public function collectSpot(int $userId, string $notional, string $feeAsset, string $reference, string $liquidityRole = 'taker', string $accountType = 'spot', array $metadata = []): LedgerTransaction
    {
        $quote = $this->fees->spot($notional, $feeAsset, $liquidityRole);

        return $this->collectFee($userId, $quote, $reference, 'spot_fee', $accountType, $metadata);
    }

    public function collectFutures(int $userId, string $notional, string $reference, string $liquidityRole = 'taker', array $metadata = []): LedgerTransaction
    {
        $quote = $this->fees->futures($notional, 'USDT', $liquidityRole);

        return $this->collectFee($userId, $quote, $reference, 'futures_fee', 'futures', $metadata);
    }

    public function collectAssessedFee(int $userId, string $feeAmount, string $asset, string $reference, string $source, string $accountType = 'funding', array $metadata = []): LedgerTransaction
    {
        $asset = strtoupper($asset);
        $quote = [
            'source' => $source,
            'asset' => $asset,
            'gross_amount' => $feeAmount,
            'fee_amount' => $feeAmount,
            'net_amount' => '0',
            'rate_bps' => 'assessed',
            'fixed_fee' => $feeAmount,
        ];

        return $this->collectFee($userId, $quote, $reference, "{$source}_fee", $accountType, $metadata);
    }

    public function collectFiatDeposit(int $userId, string $grossAmount, string $asset, string $reference, array $metadata = []): array
    {
        $quote = $this->fees->fiatDeposit($grossAmount, $asset);

        return DB::transaction(function () use ($userId, $asset, $reference, $metadata, $quote): array {
            $asset = strtoupper($asset);
            $settlement = $this->ledger->getOrCreateAccount(null, 'fiat_deposit_settlement', $asset);
            $funding = $this->ledger->getOrCreateAccount($userId, 'funding', $asset);
            $treasury = $this->ledger->getOrCreateAccount(null, 'system_treasury', $asset);

            $settlement->balance = $this->add((string) $settlement->balance, $quote['gross_amount']);
            $settlement->save();

            $tx = $this->ledger->postDoubleEntry(
                $reference,
                'Fiat deposit less service charge',
                [
                    ['account_id' => $settlement->id, 'amount' => $this->neg($quote['gross_amount']), 'asset' => $asset, 'metadata' => ['leg' => 'provider_settlement_debit']],
                    ['account_id' => $funding->id, 'amount' => $quote['net_amount'], 'asset' => $asset, 'user_id' => $userId, 'metadata' => ['leg' => 'user_net_deposit_credit']],
                    ['account_id' => $treasury->id, 'amount' => $quote['fee_amount'], 'asset' => $asset, 'metadata' => ['leg' => 'treasury_fee_credit', 'treasury_user_id' => $this->treasuryUserId()]],
                ],
                'fiat_deposit_fee',
                array_merge($metadata, $quote)
            );

            $this->creditTreasuryHotBalance($asset, $quote['fee_amount']);
            $this->logCollection($userId, 'fee.fiat_deposit_collected', $reference, $quote, $metadata);

            return ['ledger_transaction' => $tx, 'fee' => $quote, 'net_deposit' => $quote['net_amount']];
        });
    }

    private function collectFee(int $userId, array $quote, string $reference, string $transactionType, string $accountType, array $metadata): LedgerTransaction
    {
        if ($this->compare((string) $quote['fee_amount'], '0') <= 0) {
            throw new RuntimeException('Calculated fee must be greater than zero.');
        }

        return DB::transaction(function () use ($userId, $quote, $reference, $transactionType, $accountType, $metadata): LedgerTransaction {
            $asset = strtoupper((string) $quote['asset']);
            $userAccount = $this->ledger->getOrCreateAccount($userId, $accountType, $asset);
            $treasury = $this->ledger->getOrCreateAccount(null, 'system_treasury', $asset);

            $tx = $this->ledger->postDoubleEntry(
                $reference,
                'Platform fee collection',
                [
                    ['account_id' => $userAccount->id, 'amount' => $this->neg((string) $quote['fee_amount']), 'asset' => $asset, 'user_id' => $userId, 'metadata' => ['leg' => 'user_fee_debit']],
                    ['account_id' => $treasury->id, 'amount' => (string) $quote['fee_amount'], 'asset' => $asset, 'metadata' => ['leg' => 'treasury_fee_credit', 'treasury_user_id' => $this->treasuryUserId()]],
                ],
                $transactionType,
                array_merge($metadata, $quote)
            );

            $this->creditTreasuryHotBalance($asset, (string) $quote['fee_amount']);
            $this->logCollection($userId, "fee.{$transactionType}_collected", $reference, $quote, $metadata);

            return $tx;
        });
    }

    private function creditTreasuryHotBalance(string $asset, string $amount): void
    {
        $asset = strtoupper($asset);
        $balance = TreasuryBalance::query()->where('asset', $asset)->lockForUpdate()->first()
            ?? TreasuryBalance::query()->create(['asset' => $asset, 'balance' => '0', 'hot_wallet_balance' => '0', 'cold_wallet_balance' => '0']);

        $balance->balance = $this->add((string) $balance->balance, $amount);
        $balance->hot_wallet_balance = $this->add((string) $balance->hot_wallet_balance, $amount);
        $balance->save();
    }

    private function logCollection(int $userId, string $action, string $reference, array $quote, array $metadata): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'device' => request()?->userAgent(),
            'metadata' => array_merge($metadata, [
                'reference' => $reference,
                'treasury_user_id' => $this->treasuryUserId(),
                'fee' => $quote,
            ]),
        ]);

        Log::info('Platform fee collected', [
            'user_id' => $userId,
            'reference' => $reference,
            'asset' => $quote['asset'],
            'fee_amount' => $quote['fee_amount'],
            'source' => $quote['source'],
        ]);
    }

    private function neg(string $amount): string
    {
        return $this->sub('0', $amount);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
