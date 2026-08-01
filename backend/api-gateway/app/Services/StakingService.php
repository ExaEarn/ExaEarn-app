<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Staking\Services\StakingLedgerService;
use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StakingService
{
    private const SCALE = 18;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly StakingProviderRegistry $providers,
        private readonly StakingLedgerService $stakingLedger,
    ) {}

    public function listAssets(): array
    {
        return DB::table('staking_assets')
            ->whereNotIn('symbol', config('staking.excluded_native_pos_assets', []))
            ->orderBy('symbol')
            ->get()
            ->map(fn ($asset) => $this->withProviderHealth((array) $asset))
            ->all();
    }

    public function listProducts(): array
    {
        return DB::table('staking_products')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_products.staking_asset_id')
            ->whereNotIn('staking_assets.symbol', config('staking.excluded_native_pos_assets', []))
            ->select('staking_products.*', 'staking_assets.symbol', 'staking_assets.network')
            ->orderBy('staking_assets.symbol')
            ->get()
            ->map(fn ($product) => (array) $product)
            ->all();
    }

    public function productBySlug(string $slug): array
    {
        $product = DB::table('staking_products')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_products.staking_asset_id')
            ->where('staking_products.slug', $slug)
            ->whereNotIn('staking_assets.symbol', config('staking.excluded_native_pos_assets', []))
            ->select('staking_products.*', 'staking_assets.symbol', 'staking_assets.network')
            ->first();

        if (! $product) {
            throw new RuntimeException('Staking product not found.');
        }

        return (array) $product;
    }

    public function userPortfolio(int $userId): array
    {
        $rows = DB::table('staking_positions')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
            ->where('staking_positions.user_id', $userId)
            ->selectRaw('staking_assets.symbol,
                SUM(principal_amount) as principal,
                SUM(pending_stake_amount) as pending_stake,
                SUM(active_principal_amount) as active_stake,
                SUM(pending_unstake_amount) as pending_unstake,
                SUM(total_native_gross_rewards) as native_gross_rewards,
                SUM(total_native_validator_fees) as validator_fees,
                SUM(total_native_network_fees) as network_fees,
                SUM(total_native_platform_fees) as platform_commission,
                SUM(total_native_net_rewards - claimed_native_rewards) as claimable_native_rewards,
                SUM(total_exatoken_bonus_rewards - claimed_exatoken_rewards) as claimable_exatoken')
            ->groupBy('staking_assets.symbol')
            ->get();

        return $rows->map(fn ($row) => (array) $row)->all();
    }

    public function userPositions(int $userId): array
    {
        return DB::table('staking_positions')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
            ->join('staking_products', 'staking_products.id', '=', 'staking_positions.staking_product_id')
            ->where('staking_positions.user_id', $userId)
            ->select('staking_positions.*', 'staking_assets.symbol', 'staking_assets.network', 'staking_products.name as product_name')
            ->latest('staking_positions.created_at')
            ->get()
            ->map(fn ($position) => (array) $position)
            ->all();
    }

    public function createPosition(int $userId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $payload): array {
            $product = DB::table('staking_products')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_products.staking_asset_id')
                ->where('staking_products.id', (int) $payload['staking_product_id'])
                ->select('staking_products.*', 'staking_assets.symbol', 'staking_assets.network', 'staking_assets.readiness_status', 'staking_assets.native_staking_enabled', 'staking_assets.new_positions_enabled', 'staking_assets.emergency_paused')
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw new RuntimeException('Staking product not found.');
            }

            $symbol = strtoupper((string) $product->symbol);
            if (in_array($symbol, config('staking.excluded_native_pos_assets', []), true)) {
                throw new RuntimeException("{$symbol} is not available for Native PoS Staking.");
            }

            if ($product->status !== 'active' || ! $product->native_staking_enabled || ! $product->new_positions_enabled || $product->emergency_paused) {
                throw new RuntimeException('This staking product is not accepting positions.');
            }

            if (! in_array($product->readiness_status, ['testnet', 'integration_testing', 'internal_testing', 'limited_release', 'production'], true)) {
                throw new RuntimeException('The staking provider has not passed readiness checks.');
            }

            $amount = $this->normalizeAmount((string) $payload['amount']);
            $this->assertAmountRules($amount, (string) $product->minimum_amount, $product->maximum_amount ? (string) $product->maximum_amount : null);

            $providerHealth = $this->providers->forSymbol($symbol)->healthCheck();
            if (($providerHealth['ready'] ?? false) !== true) {
                throw new RuntimeException('The staking provider is not healthy: '.($providerHealth['status'] ?? 'unknown'));
            }

            $available = $this->ledger->getBalance($userId, $symbol, 'funding');
            if ($this->compare($available, $amount) < 0) {
                throw new RuntimeException('Insufficient available balance.');
            }

            $idempotencyKey = (string) $payload['idempotency_key'];
            $existing = DB::table('staking_positions')->where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return (array) $existing;
            }

            $funding = $this->ledger->getOrCreateAccount($userId, 'funding', $symbol);
            $pending = $this->ledger->getOrCreateAccount($userId, 'staking_pending', $symbol);
            $ledgerTx = $this->ledger->postDoubleEntry(
                "staking:reserve:{$userId}:{$idempotencyKey}",
                'Reserve principal for staking',
                [
                    ['account_id' => $funding->id, 'amount' => $this->sub('0', $amount), 'asset' => $symbol, 'user_id' => $userId],
                    ['account_id' => $pending->id, 'amount' => $amount, 'asset' => $symbol, 'user_id' => $userId],
                ],
                'staking_reservation',
                ['staking_product_id' => $product->id]
            );

            $publicId = (string) Str::uuid();
            $positionId = DB::table('staking_positions')->insertGetId([
                'public_id' => $publicId,
                'user_id' => $userId,
                'staking_product_id' => $product->id,
                'staking_asset_id' => $product->staking_asset_id,
                'principal_amount' => $amount,
                'pending_stake_amount' => $amount,
                'status' => 'pending',
                'auto_compound_enabled' => (bool) ($payload['auto_compound'] ?? false),
                'opened_at' => now(),
                'terms_version' => (string) $payload['terms_version'],
                'source' => 'api',
                'idempotency_key' => $idempotencyKey,
                'metadata' => json_encode(['ledger_transaction_id' => $ledgerTx->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('staking_transactions')->insert([
                'public_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'staking_position_id' => $positionId,
                'staking_asset_id' => $product->staking_asset_id,
                'transaction_type' => 'stake_reservation',
                'amount' => $amount,
                'fee_amount' => '0',
                'net_amount' => $amount,
                'ledger_transaction_id' => $ledgerTx->id,
                'status' => 'completed',
                'idempotency_key' => "staking_tx:reserve:{$userId}:{$idempotencyKey}",
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('staking_positions')->where('id', $positionId)->first();
        });
    }

    public function requestUnstake(int $userId, string $publicId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $publicId, $payload): array {
            $position = DB::table('staking_positions')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
                ->where('staking_positions.user_id', $userId)
                ->where('staking_positions.public_id', $publicId)
                ->select('staking_positions.*', 'staking_assets.symbol', 'staking_assets.unstaking_enabled', 'staking_assets.emergency_paused')
                ->lockForUpdate()
                ->first();

            if (! $position) {
                throw new RuntimeException('Staking position not found.');
            }
            if ($position->status !== 'active') {
                throw new RuntimeException('Only active positions can be unstaked.');
            }
            if (! $position->unstaking_enabled || $position->emergency_paused) {
                throw new RuntimeException('Unstaking is paused for this network.');
            }

            $amount = $this->normalizeAmount((string) ($payload['amount'] ?? $position->active_principal_amount));
            if ($this->compare($amount, '0') <= 0 || $this->compare($amount, (string) $position->active_principal_amount) > 0) {
                throw new RuntimeException('Invalid unstake amount.');
            }

            $idempotencyKey = (string) $payload['idempotency_key'];
            $existing = DB::table('staking_unstake_requests')->where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return (array) $existing;
            }

            $this->stakingLedger->reserveActivePrincipalForUnstaking((int) $position->id, $amount, $idempotencyKey);

            $requestId = DB::table('staking_unstake_requests')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'staking_position_id' => $position->id,
                'user_id' => $userId,
                'requested_amount' => $amount,
                'status' => 'pending',
                'requested_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'metadata' => json_encode(['release_requires_network_confirmation' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pendingUnstakeAmount = $this->add((string) $position->pending_unstake_amount, $amount);
            $activePrincipalAmount = $this->sub((string) $position->active_principal_amount, $amount);
            DB::table('staking_positions')->where('id', $position->id)->update([
                'pending_unstake_amount' => $pendingUnstakeAmount,
                'active_principal_amount' => $activePrincipalAmount,
                'status' => $this->compare($activePrincipalAmount, '0') <= 0 ? 'unstaking' : 'partial_unstake_pending',
                'unstaking_requested_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('staking_unstake_requests')->where('id', $requestId)->first();
        });
    }

    public function acceptTerms(int $userId, string $termsVersion, array $metadata = []): void
    {
        DB::table('staking_terms_acceptances')->updateOrInsert(
            ['user_id' => $userId, 'terms_version' => $termsVersion],
            ['status' => 'accepted', 'metadata' => json_encode($metadata), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function terms(): array
    {
        return [
            'terms_version' => 'staking-v1',
            'native_rewards_source' => 'verified blockchain or approved provider settlements only',
            'excluded_native_pos_assets' => config('staking.excluded_native_pos_assets'),
            'mainnet_activation' => 'dual-admin approval required per network',
        ];
    }

    public function userRewards(int $userId): array
    {
        return DB::table('staking_reward_allocations')
            ->join('staking_reward_batches', 'staking_reward_batches.id', '=', 'staking_reward_allocations.staking_reward_batch_id')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_reward_batches.staking_asset_id')
            ->where('staking_reward_allocations.user_id', $userId)
            ->select('staking_reward_allocations.*', 'staking_assets.symbol', 'staking_reward_batches.period_start', 'staking_reward_batches.period_end')
            ->latest('staking_reward_allocations.id')
            ->limit(100)
            ->get()
            ->map(fn ($reward) => (array) $reward)
            ->all();
    }

    public function userTransactions(int $userId): array
    {
        return DB::table('staking_transactions')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_transactions.staking_asset_id')
            ->where('staking_transactions.user_id', $userId)
            ->select('staking_transactions.*', 'staking_assets.symbol')
            ->latest('staking_transactions.id')
            ->limit(100)
            ->get()
            ->map(fn ($transaction) => (array) $transaction)
            ->all();
    }

    public function apyHistory(): array
    {
        return DB::table('staking_apy_history')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_apy_history.staking_asset_id')
            ->whereNotIn('staking_assets.symbol', config('staking.excluded_native_pos_assets', []))
            ->select('staking_apy_history.*', 'staking_assets.symbol', DB::raw('staking_apy_history.created_at as recorded_at'))
            ->latest('staking_apy_history.created_at')
            ->limit(250)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function exaTokenCampaigns(): array
    {
        return DB::table('exatoken_staking_campaigns')
            ->whereIn('status', ['active', 'scheduled'])
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn ($campaign) => (array) $campaign)
            ->all();
    }

    public function networkStatuses(): array
    {
        return DB::table('staking_network_statuses')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_network_statuses.staking_asset_id')
            ->whereNotIn('staking_assets.symbol', config('staking.excluded_native_pos_assets', []))
            ->select('staking_network_statuses.*', 'staking_assets.symbol', 'staking_assets.network', DB::raw('staking_network_statuses.created_at as checked_at'))
            ->latest('staking_network_statuses.created_at')
            ->limit(100)
            ->get()
            ->map(fn ($status) => (array) $status)
            ->all();
    }

    public function unbondingEstimates(): array
    {
        return DB::table('staking_assets')
            ->whereNotIn('symbol', config('staking.excluded_native_pos_assets', []))
            ->select('symbol', 'network', 'unbonding_period_seconds', 'expected_activation_seconds', 'supports_partial_unstaking', 'metadata')
            ->orderBy('symbol')
            ->get()
            ->map(fn ($asset) => (array) $asset)
            ->all();
    }

    public function claimNativeRewards(int $userId, string $publicId): array
    {
        $position = DB::table('staking_positions')
            ->where('user_id', $userId)
            ->where('public_id', $publicId)
            ->first();

        if (! $position) {
            throw new RuntimeException('Staking position not found.');
        }

        $claimable = $this->sub((string) $position->total_native_net_rewards, (string) $position->claimed_native_rewards);
        if ($this->compare($claimable, '0') <= 0) {
            throw new RuntimeException('No verified native staking rewards are currently claimable.');
        }

        throw new RuntimeException('Native reward claiming requires a reconciled reward allocation and ledger distribution job.');
    }

    public function claimExaTokenRewards(int $userId, string $publicId): array
    {
        $position = DB::table('staking_positions')
            ->where('user_id', $userId)
            ->where('public_id', $publicId)
            ->first();

        if (! $position) {
            throw new RuntimeException('Staking position not found.');
        }

        $claimable = $this->sub((string) $position->total_exatoken_bonus_rewards, (string) $position->claimed_exatoken_rewards);
        if ($this->compare($claimable, '0') <= 0) {
            throw new RuntimeException('No funded ExaToken staking bonuses are currently claimable.');
        }

        throw new RuntimeException('ExaToken bonus claiming requires a funded reserve allocation and ledger distribution job.');
    }

    public function updateAutoCompound(int $userId, string $publicId, bool $enabled): array
    {
        return DB::transaction(function () use ($userId, $publicId, $enabled): array {
            $position = DB::table('staking_positions')
                ->join('staking_products', 'staking_products.id', '=', 'staking_positions.staking_product_id')
                ->where('staking_positions.user_id', $userId)
                ->where('staking_positions.public_id', $publicId)
                ->select('staking_positions.*', 'staking_products.auto_compound_supported')
                ->lockForUpdate()
                ->first();

            if (! $position) {
                throw new RuntimeException('Staking position not found.');
            }
            if (! $position->auto_compound_supported && $enabled) {
                throw new RuntimeException('Auto-compounding is not supported for this staking product.');
            }

            DB::table('staking_positions')->where('id', $position->id)->update([
                'auto_compound_enabled' => $enabled,
                'updated_at' => now(),
            ]);

            return (array) DB::table('staking_positions')->where('id', $position->id)->first();
        });
    }

    private function withProviderHealth(array $asset): array
    {
        try {
            $asset['provider_health'] = $this->providers->forSymbol((string) $asset['symbol'])->healthCheck();
        } catch (\Throwable $exception) {
            $asset['provider_health'] = ['ready' => false, 'status' => 'provider_missing', 'message' => $exception->getMessage()];
        }

        return $asset;
    }

    private function assertAmountRules(string $amount, string $minimum, ?string $maximum): void
    {
        if ($this->compare($amount, $minimum) < 0) {
            throw new RuntimeException('Amount is below the product minimum.');
        }
        if ($maximum !== null && $this->compare($amount, $maximum) > 0) {
            throw new RuntimeException('Amount exceeds the product maximum.');
        }
    }

    private function normalizeAmount(string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new RuntimeException('Invalid amount.');
        }

        return function_exists('bcadd') ? bcadd($amount, '0', self::SCALE) : number_format((float) $amount, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        return (float) $a <=> (float) $b;
    }
}

