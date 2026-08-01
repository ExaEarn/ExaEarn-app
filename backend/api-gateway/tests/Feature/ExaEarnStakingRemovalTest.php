<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Staking\Contracts\SecureSignerInterface;
use App\Domain\Staking\Contracts\StakingProviderInterface;
use App\Domain\Staking\Services\StakingLedgerService;
use App\Domain\Staking\Services\StakingProviderRegistry;
use App\Jobs\ActivateStakingPositions;
use App\Jobs\CreateDelegationBatch;
use App\Jobs\DistributeNativeStakingRewards;
use App\Jobs\MonitorDelegationConfirmation;
use App\Jobs\MonitorStakeActivation;
use App\Jobs\ReleaseUnstakedPrincipal;
use App\Models\Account;
use App\Models\Admin;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaEarnStakingRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_xrp_is_not_listed_as_native_pos_staking_asset(): void
    {
        $response = $this->getJson('/api/v1/staking/assets');

        $response->assertOk();

        $symbols = collect($response->json('data'))->pluck('symbol')->all();
        $this->assertNotContains('XRP', $symbols);
        $this->assertContains('SOL', $symbols);
        $this->assertContains('ETH', $symbols);
    }

    public function test_legacy_staking_routes_are_removed(): void
    {
        $this->postJson('/api/staking/stake', [
            'pool_id' => 1,
            'amount' => '10',
        ])->assertGone()
            ->assertJsonFragment([
                'message' => 'Legacy XRP/paper staking has been removed. Use /api/v1/staking for ExaEarn Native PoS Staking.',
            ]);
    }

    public function test_position_creation_fails_closed_without_ready_provider(): void
    {
        $assetId = \DB::table('staking_assets')->where('symbol', 'SOL')->value('id');
        $productId = \DB::table('staking_products')->insertGetId([
            'staking_asset_id' => $assetId,
            'name' => 'SOL Testnet Native Staking',
            'slug' => 'sol-testnet-native-staking',
            'product_type' => 'native_pos',
            'status' => 'active',
            'network_environment' => 'testnet',
            'minimum_amount' => '1',
            'reward_schedule' => 'verified_network_rewards',
            'redemption_type' => 'network_unbonding',
            'terms_version' => 'staking-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('staking_assets')->where('id', $assetId)->update([
            'native_staking_enabled' => true,
            'new_positions_enabled' => true,
            'emergency_paused' => false,
            'readiness_status' => 'testnet',
        ]);

        $this->postJson('/api/v1/staking/positions', [
            'staking_product_id' => $productId,
            'amount' => '1',
            'auto_compound' => false,
            'terms_version' => 'staking-v1',
            'idempotency_key' => 'test-sol-position-1',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'The staking provider is not healthy: configuration_required']);
    }

    public function test_legacy_staking_tables_are_not_recreated_on_fresh_migration(): void
    {
        $this->assertFalse(\Schema::hasTable('staking_pools'));
        $this->assertFalse(\Schema::hasTable('user_stakes'));
        $this->assertFalse(\Schema::hasTable('staking_rewards'));
    }

    public function test_admin_mainnet_activation_requires_second_approval(): void
    {
        $roleId = \DB::table('roles')->insertGetId(['name' => 'super_admin', 'created_at' => now(), 'updated_at' => now()]);
        $requestingAdmin = Admin::query()->create([
            'name' => 'Requester',
            'email' => 'requester@example.test',
            'password' => 'secret',
            'role_id' => $roleId,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
        $approvingAdmin = Admin::query()->create([
            'name' => 'Approver',
            'email' => 'approver@example.test',
            'password' => 'secret',
            'role_id' => $roleId,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $assetId = \DB::table('staking_assets')->where('symbol', 'SOL')->value('id');

        $request = $this->actingAs($requestingAdmin)->postJson("/api/admin/v1/staking/assets/{$assetId}/request-mainnet-activation", [
            'reason' => 'All testnet checks passed in controlled evidence package.',
            'evidence' => [
                'delegation' => true,
                'activation' => true,
                'rewards' => true,
                'unstaking' => true,
                'reconciliation' => true,
            ],
        ]);

        $request->assertAccepted();
        $publicId = $request->json('data.public_id');

        $this->actingAs($requestingAdmin)
            ->postJson("/api/admin/v1/staking/approvals/{$publicId}/decision", ['decision' => 'approve'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Dual approval requires a different administrator.']);

        $this->actingAs($approvingAdmin)
            ->postJson("/api/admin/v1/staking/approvals/{$publicId}/decision", ['decision' => 'approve'])
            ->assertOk();

        $this->assertDatabaseHas('staking_assets', [
            'id' => $assetId,
            'mainnet_enabled' => true,
            'readiness_status' => 'production',
        ]);
    }

    public function test_reward_claims_fail_closed_without_verified_allocations(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        $assetId = \DB::table('staking_assets')->where('symbol', 'SOL')->value('id');
        $productId = $this->createNativeProduct($assetId);
        $publicId = (string) Str::uuid();

        \DB::table('staking_positions')->insert([
            'public_id' => $publicId,
            'user_id' => $user->id,
            'staking_product_id' => $productId,
            'staking_asset_id' => $assetId,
            'principal_amount' => '10',
            'active_principal_amount' => '10',
            'status' => 'active',
            'opened_at' => now(),
            'terms_version' => 'staking-v1',
            'source' => 'test',
            'idempotency_key' => 'claim-fail-closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/staking/positions/{$publicId}/claim-native-rewards")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No verified native staking rewards are currently claimable.']);

        $this->actingAs($user)
            ->postJson("/api/v1/staking/positions/{$publicId}/claim-exatoken-rewards")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No funded ExaToken staking bonuses are currently claimable.']);
    }

    public function test_user_staking_history_endpoints_are_table_backed(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        $assetId = \DB::table('staking_assets')->where('symbol', 'ATOM')->value('id');
        $productId = $this->createNativeProduct($assetId, 'atom-testnet-native-staking');
        $positionId = \DB::table('staking_positions')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'staking_product_id' => $productId,
            'staking_asset_id' => $assetId,
            'principal_amount' => '25',
            'active_principal_amount' => '25',
            'total_native_net_rewards' => '0.125',
            'status' => 'active',
            'opened_at' => now(),
            'terms_version' => 'staking-v1',
            'source' => 'test',
            'idempotency_key' => 'history-position',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('staking_transactions')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'staking_position_id' => $positionId,
            'staking_asset_id' => $assetId,
            'transaction_type' => 'native_reward_reconciled',
            'amount' => '0.125',
            'fee_amount' => '0',
            'net_amount' => '0.125',
            'status' => 'completed',
            'idempotency_key' => 'history-transaction',
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/staking/transactions')
            ->assertOk()
            ->assertJsonFragment(['symbol' => 'ATOM'])
            ->assertJsonFragment(['transaction_type' => 'native_reward_reconciled']);

        $this->actingAs($user)
            ->getJson('/api/v1/staking/portfolio')
            ->assertOk()
            ->assertJsonFragment(['symbol' => 'ATOM']);
    }

    public function test_admin_observation_endpoints_return_staking_tables(): void
    {
        $roleId = \DB::table('roles')->insertGetId(['name' => 'observer', 'created_at' => now(), 'updated_at' => now()]);
        $permissionId = \DB::table('permissions')->insertGetId(['name' => 'staking.manage_wallets', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]);

        $admin = Admin::query()->firstOrCreate(
            ['email' => 'observer@example.test'],
            [
                'name' => 'Observer',
                'password' => 'secret',
                'role_id' => $roleId,
                'status' => 'active',
                'two_factor_enabled' => true,
            ]
        );
        $assetId = \DB::table('staking_assets')->where('symbol', 'NEAR')->value('id');

        \DB::table('staking_wallets')->insert([
            'staking_asset_id' => $assetId,
            'network' => 'near',
            'network_environment' => 'testnet',
            'wallet_address' => 'near-staking.testnet',
            'wallet_type' => 'staking',
            'custody_provider' => 'test-custody',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/v1/staking/wallets')
            ->assertOk()
            ->assertJsonFragment(['symbol' => 'NEAR'])
            ->assertJsonFragment(['wallet_address' => 'near-staking.testnet']);
    }

    public function test_verified_delegation_activation_moves_principal_to_active_liability(): void
    {
        $ledger = app(LedgerService::class);
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'SOL')->value('id');
        $productId = $this->createNativeProduct($assetId, 'sol-activation-product');
        $positionId = $this->createPendingPosition($user->id, $assetId, $productId, 'activation-position', '10');

        $funding = $ledger->getOrCreateAccount($user->id, 'funding', 'SOL');
        $funding->forceFill(['balance' => '10'])->save();
        $pending = $ledger->getOrCreateAccount($user->id, 'staking_pending', 'SOL');
        $ledger->postDoubleEntry('test:fund-pending-sol', 'Seed pending staking balance', [
            ['account_id' => $funding->id, 'amount' => '-10', 'asset' => 'SOL', 'user_id' => $user->id],
            ['account_id' => $pending->id, 'amount' => '10', 'asset' => 'SOL', 'user_id' => $user->id],
        ], 'test');

        $validatorId = $this->createValidator($assetId);
        $walletId = $this->createStakingWallet($assetId, 'SOL');
        $batchId = \DB::table('staking_delegation_batches')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'staking_asset_id' => $assetId,
            'validator_id' => $validatorId,
            'staking_wallet_id' => $walletId,
            'network_environment' => 'testnet',
            'total_amount' => '10',
            'position_count' => 1,
            'status' => 'activated',
            'idempotency_key' => 'activation-batch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('staking_delegation_allocations')->insert([
            'staking_delegation_batch_id' => $batchId,
            'staking_position_id' => $positionId,
            'allocated_amount' => '10',
            'status' => 'allocated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ActivateStakingPositions::class)->handle(app(StakingLedgerService::class));

        $this->assertSame('0.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_pending')->where('asset', 'SOL')->value('balance'));
        $this->assertSame('10.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_active')->where('asset', 'SOL')->value('balance'));
        $this->assertDatabaseHas('staking_positions', ['id' => $positionId, 'status' => 'active']);
    }

    public function test_unstaking_reserves_active_principal_and_release_requires_withdrawable_status(): void
    {
        $ledger = app(LedgerService::class);
        $user = User::factory()->create(['two_factor_enabled' => false]);
        $assetId = \DB::table('staking_assets')->where('symbol', 'ATOM')->value('id');
        $productId = $this->createNativeProduct($assetId, 'atom-unstake-product');
        \DB::table('staking_assets')->where('id', $assetId)->update(['unstaking_enabled' => true, 'emergency_paused' => false]);
        $publicId = (string) Str::uuid();
        $positionId = $this->createActivePosition($user->id, $assetId, $productId, $publicId, '25');

        $active = $ledger->getOrCreateAccount($user->id, 'staking_active', 'ATOM');
        $active->forceFill(['balance' => '25'])->save();

        $this->actingAs($user)
            ->postJson("/api/v1/staking/positions/{$publicId}/unstake", [
                'amount' => '5',
                'idempotency_key' => 'unstake-reservation',
            ])
            ->assertAccepted();

        $requestId = \DB::table('staking_unstake_requests')->where('staking_position_id', $positionId)->value('id');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Principal cannot be released before withdrawable confirmation.');
        app(StakingLedgerService::class)->releaseUnstakedPrincipal((int) $requestId);
    }

    public function test_withdrawable_unstake_request_releases_principal_to_available_balance(): void
    {
        $ledger = app(LedgerService::class);
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'DOT')->value('id');
        $productId = $this->createNativeProduct($assetId, 'dot-release-product');
        $positionId = $this->createActivePosition($user->id, $assetId, $productId, (string) Str::uuid(), '10');
        $pending = $ledger->getOrCreateAccount($user->id, 'staking_pending_unstake', 'DOT');
        $pending->forceFill(['balance' => '4'])->save();

        \DB::table('staking_positions')->where('id', $positionId)->update([
            'active_principal_amount' => '6',
            'pending_unstake_amount' => '4',
            'status' => 'unbonding',
        ]);
        $requestId = \DB::table('staking_unstake_requests')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'staking_position_id' => $positionId,
            'user_id' => $user->id,
            'requested_amount' => '4',
            'status' => 'withdrawable',
            'requested_at' => now(),
            'confirmed_withdrawable_at' => now(),
            'idempotency_key' => 'dot-release',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ReleaseUnstakedPrincipal::class)->handle(app(StakingLedgerService::class));

        $this->assertSame('0.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_pending_unstake')->where('asset', 'DOT')->value('balance'));
        $this->assertSame('4.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'DOT')->value('balance'));
        $this->assertDatabaseHas('staking_unstake_requests', ['id' => $requestId, 'status' => 'released']);
    }

    public function test_approved_native_reward_allocation_distributes_to_payable_balance(): void
    {
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'NEAR')->value('id');
        $productId = $this->createNativeProduct($assetId, 'near-reward-product');
        $positionId = $this->createActivePosition($user->id, $assetId, $productId, (string) Str::uuid(), '100');
        $batchId = \DB::table('staking_reward_batches')->insertGetId([
            'staking_asset_id' => $assetId,
            'network_environment' => 'testnet',
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'gross_native_reward' => '1',
            'validator_fee' => '0.1',
            'network_cost' => '0',
            'provider_fee' => '0',
            'platform_commission' => '0.1',
            'distributable_native_reward' => '0.8',
            'blockchain_reference' => 'near-reward-test-1',
            'status' => 'approved',
            'calculation_hash' => hash('sha256', 'near-reward-test-1'),
            'source_data_hash' => hash('sha256', 'near-source-test-1'),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $allocationId = \DB::table('staking_reward_allocations')->insertGetId([
            'staking_reward_batch_id' => $batchId,
            'staking_position_id' => $positionId,
            'user_id' => $user->id,
            'eligible_principal' => '100',
            'eligible_seconds_or_epochs' => '1',
            'weighted_share' => '1',
            'gross_native_reward' => '1',
            'validator_fee_share' => '0.1',
            'network_fee_share' => '0',
            'platform_fee' => '0.1',
            'net_native_reward' => '0.8',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DistributeNativeStakingRewards::class)->handle(app(StakingLedgerService::class));

        $this->assertSame('0.800000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_reward_payable')->where('asset', 'NEAR')->value('balance'));
        $this->assertDatabaseHas('staking_reward_allocations', ['id' => $allocationId, 'status' => 'distributed']);
    }

    public function test_secure_signer_fails_closed_without_configuration(): void
    {
        config()->set('services.staking_secure_signer.url', null);
        config()->set('services.staking_secure_signer.key_reference', null);
        config()->set('services.staking_secure_signer.secret', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Secure staking signer is not configured.');

        app(SecureSignerInterface::class)
            ->requestSignature('SOL', 'solana-devnet', ['message' => 'unsigned'], 'signer-missing-config');
    }

    public function test_secure_signer_posts_unsigned_payload_with_idempotency(): void
    {
        config()->set('services.staking_secure_signer.url', 'https://signer.example.test');
        config()->set('services.staking_secure_signer.key_reference', 'kms://staking/sol');
        config()->set('services.staking_secure_signer.secret', 'test-secret');
        Http::fake([
            'signer.example.test/sign' => Http::response([
                'signed_payload' => 'signed-by-test-signer',
                'signing_reference' => 'sig-ref-1',
                'status' => 'signed',
            ]),
        ]);

        $result = app(SecureSignerInterface::class)
            ->requestSignature('SOL', 'solana-devnet', ['message' => 'unsigned'], 'signer-idempotency-1');

        $this->assertSame('signed-by-test-signer', $result['signed_payload']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key', 'signer-idempotency-1')
            && $request->hasHeader('X-Signer-Secret', 'test-secret')
            && $request['key_reference'] === 'kms://staking/sol'
            && $request['asset'] === 'SOL');
    }

    public function test_delegation_batch_builds_signs_and_submits_transaction(): void
    {
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'SOL')->value('id');
        $productId = $this->createNativeProduct($assetId, 'sol-batch-product');
        \DB::table('staking_assets')->where('id', $assetId)->update([
            'native_staking_enabled' => true,
            'new_positions_enabled' => true,
            'emergency_paused' => false,
        ]);
        $positionId = $this->createPendingPosition($user->id, $assetId, $productId, 'batch-position', '12.5');
        $validatorId = $this->createValidator($assetId);
        $walletId = $this->createStakingWallet($assetId, 'SOL');

        $provider = \Mockery::mock(StakingProviderInterface::class);
        $provider->shouldReceive('healthCheck')->once()->andReturn(['ready' => true, 'status' => 'healthy']);
        $provider->shouldReceive('network')->once()->andReturn('solana-devnet');
        $provider->shouldReceive('buildDelegationTransaction')
            ->once()
            ->with(\Mockery::on(fn (array $delegation): bool => $delegation['amount'] === '12.500000000000000000'
                && (int) $delegation['validator']['id'] === $validatorId
                && (int) $delegation['wallet']['id'] === $walletId))
            ->andReturn(['payload_reference' => 'unsigned-sol-batch-1', 'message' => 'unsigned-sol']);
        $provider->shouldReceive('submitSignedTransaction')
            ->once()
            ->with('signed-sol-payload', \Mockery::type('array'))
            ->andReturn(['transaction_hash' => 'sol-testnet-tx-1']);

        $registry = \Mockery::mock(StakingProviderRegistry::class);
        $registry->shouldReceive('forSymbol')->with('SOL')->andReturn($provider);
        $signer = \Mockery::mock(SecureSignerInterface::class);
        $signer->shouldReceive('requestSignature')
            ->once()
            ->with('SOL', 'solana-devnet', \Mockery::type('array'), \Mockery::type('string'))
            ->andReturn(['signed_payload' => 'signed-sol-payload', 'signing_reference' => 'signer-ref-1', 'status' => 'signed']);

        app(CreateDelegationBatch::class)->handle($registry, $signer);

        $this->assertDatabaseHas('staking_delegation_batches', [
            'staking_asset_id' => $assetId,
            'validator_id' => $validatorId,
            'staking_wallet_id' => $walletId,
            'status' => 'delegation_submitted',
            'unsigned_payload_reference' => 'unsigned-sol-batch-1',
            'signing_request_reference' => 'signer-ref-1',
            'blockchain_transaction_hash' => 'sol-testnet-tx-1',
        ]);
        $this->assertDatabaseHas('staking_delegation_allocations', [
            'staking_position_id' => $positionId,
            'allocated_amount' => '12.500000000000000000',
            'status' => 'allocated',
        ]);
        $this->assertDatabaseHas('staking_positions', [
            'id' => $positionId,
            'status' => 'delegation_submitted',
        ]);
        $this->assertDatabaseHas('staking_transactions', [
            'staking_asset_id' => $assetId,
            'transaction_type' => 'delegation',
            'blockchain_transaction_hash' => 'sol-testnet-tx-1',
            'status' => 'submitted',
        ]);
    }

    public function test_delegation_batch_fails_closed_without_wallet_fee_balance(): void
    {
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'ATOM')->value('id');
        $productId = $this->createNativeProduct($assetId, 'atom-batch-product');
        \DB::table('staking_assets')->where('id', $assetId)->update([
            'native_staking_enabled' => true,
            'new_positions_enabled' => true,
            'emergency_paused' => false,
        ]);
        $positionId = $this->createPendingPosition($user->id, $assetId, $productId, 'blocked-batch-position', '3');
        $this->createValidator($assetId);
        $walletId = $this->createStakingWallet($assetId, 'ATOM');
        \DB::table('staking_wallets')->where('id', $walletId)->update(['fee_balance' => '0']);

        $provider = \Mockery::mock(StakingProviderInterface::class);
        $provider->shouldReceive('healthCheck')->once()->andReturn(['ready' => true, 'status' => 'healthy']);
        $provider->shouldNotReceive('buildDelegationTransaction');
        $registry = \Mockery::mock(StakingProviderRegistry::class);
        $registry->shouldReceive('forSymbol')->with('ATOM')->andReturn($provider);

        app(CreateDelegationBatch::class)->handle($registry, \Mockery::mock(SecureSignerInterface::class));

        $this->assertDatabaseMissing('staking_delegation_batches', ['staking_asset_id' => $assetId]);
        $this->assertDatabaseHas('staking_positions', ['id' => $positionId, 'status' => 'batching']);
        $this->assertDatabaseHas('staking_audit_logs', [
            'staking_asset_id' => $assetId,
            'status' => 'delegation_batch_blocked',
        ]);
    }

    public function test_confirmed_delegation_can_be_verified_and_activated(): void
    {
        $ledger = app(LedgerService::class);
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'SUI')->value('id');
        $productId = $this->createNativeProduct($assetId, 'sui-confirmation-product');
        $positionId = $this->createPendingPosition($user->id, $assetId, $productId, 'sui-confirmation-position', '8');
        \DB::table('staking_positions')->where('id', $positionId)->update(['status' => 'delegation_submitted']);
        $pending = $ledger->getOrCreateAccount($user->id, 'staking_pending', 'SUI');
        $pending->forceFill(['balance' => '8'])->save();
        $validatorId = $this->createValidator($assetId);
        $walletId = $this->createStakingWallet($assetId, 'SUI');
        $batchId = $this->createSubmittedDelegationBatch($assetId, $validatorId, $walletId, $positionId, '8', 'sui-testnet-tx-1');

        $provider = \Mockery::mock(StakingProviderInterface::class);
        $provider->shouldReceive('monitorConfirmation')
            ->once()
            ->with('sui-testnet-tx-1')
            ->andReturn(['status' => 'confirmed', 'confirmation_count' => 12, 'block_or_slot' => 'checkpoint-10']);
        $provider->shouldReceive('verifyDelegation')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn(['active' => true, 'active_amount' => '8', 'activation_reference' => 'epoch-11']);
        $registry = \Mockery::mock(StakingProviderRegistry::class);
        $registry->shouldReceive('forSymbol')->with('SUI')->andReturn($provider);

        app(MonitorDelegationConfirmation::class)->handle($registry, app(StakingLedgerService::class));
        app(MonitorStakeActivation::class)->handle($registry);
        app(ActivateStakingPositions::class)->handle(app(StakingLedgerService::class));

        $this->assertDatabaseHas('staking_delegation_batches', ['id' => $batchId, 'status' => 'activated']);
        $this->assertDatabaseHas('staking_delegations', ['provider_reference' => "delegation-batch:{$batchId}", 'status' => 'active']);
        $this->assertDatabaseHas('staking_positions', ['id' => $positionId, 'status' => 'active']);
        $this->assertSame('8.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_active')->where('asset', 'SUI')->value('balance'));
    }

    public function test_failed_delegation_confirmation_reverses_pending_principal(): void
    {
        $ledger = app(LedgerService::class);
        $user = User::factory()->create();
        $assetId = \DB::table('staking_assets')->where('symbol', 'XTZ')->value('id');
        $productId = $this->createNativeProduct($assetId, 'xtz-failed-confirmation-product');
        $positionId = $this->createPendingPosition($user->id, $assetId, $productId, 'xtz-failed-position', '5');
        \DB::table('staking_positions')->where('id', $positionId)->update(['status' => 'delegation_submitted']);
        $pending = $ledger->getOrCreateAccount($user->id, 'staking_pending', 'XTZ');
        $pending->forceFill(['balance' => '5'])->save();
        $validatorId = $this->createValidator($assetId);
        $walletId = $this->createStakingWallet($assetId, 'XTZ');
        $batchId = $this->createSubmittedDelegationBatch($assetId, $validatorId, $walletId, $positionId, '5', 'xtz-failed-tx-1');

        $provider = \Mockery::mock(StakingProviderInterface::class);
        $provider->shouldReceive('monitorConfirmation')
            ->once()
            ->with('xtz-failed-tx-1')
            ->andReturn(['status' => 'failed', 'message' => 'Rejected by baker operation precheck.']);
        $registry = \Mockery::mock(StakingProviderRegistry::class);
        $registry->shouldReceive('forSymbol')->with('XTZ')->andReturn($provider);

        app(MonitorDelegationConfirmation::class)->handle($registry, app(StakingLedgerService::class));

        $this->assertDatabaseHas('staking_delegation_batches', ['id' => $batchId, 'status' => 'failed']);
        $this->assertDatabaseHas('staking_positions', ['id' => $positionId, 'status' => 'failed']);
        $this->assertSame('0.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'staking_pending')->where('asset', 'XTZ')->value('balance'));
        $this->assertSame('5.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'XTZ')->value('balance'));
    }

    private function createNativeProduct(int $assetId, string $slug = 'sol-test-product'): int
    {
        return \DB::table('staking_products')->insertGetId([
            'staking_asset_id' => $assetId,
            'name' => 'Native Test Staking',
            'slug' => $slug,
            'product_type' => 'native_pos',
            'status' => 'active',
            'network_environment' => 'testnet',
            'minimum_amount' => '1',
            'reward_schedule' => 'verified_network_rewards',
            'redemption_type' => 'network_unbonding',
            'terms_version' => 'staking-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPendingPosition(int $userId, int $assetId, int $productId, string $key, string $amount): int
    {
        return \DB::table('staking_positions')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'staking_product_id' => $productId,
            'staking_asset_id' => $assetId,
            'principal_amount' => $amount,
            'pending_stake_amount' => $amount,
            'status' => 'batching',
            'opened_at' => now(),
            'terms_version' => 'staking-v1',
            'source' => 'test',
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createActivePosition(int $userId, int $assetId, int $productId, string $publicId, string $amount): int
    {
        return \DB::table('staking_positions')->insertGetId([
            'public_id' => $publicId,
            'user_id' => $userId,
            'staking_product_id' => $productId,
            'staking_asset_id' => $assetId,
            'principal_amount' => $amount,
            'active_principal_amount' => $amount,
            'status' => 'active',
            'opened_at' => now(),
            'activation_at' => now(),
            'terms_version' => 'staking-v1',
            'source' => 'test',
            'idempotency_key' => "active-{$publicId}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createValidator(int $assetId): int
    {
        return \DB::table('staking_validators')->insertGetId([
            'staking_asset_id' => $assetId,
            'provider_identifier' => 'validator-'.Str::uuid(),
            'validator_name' => 'Test Validator',
            'validator_address' => 'validator-address',
            'commission_rate' => '0',
            'status' => 'active',
            'allowlisted' => true,
            'delegated_amount' => '0',
            'jailed_or_delinquent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStakingWallet(int $assetId, string $symbol): int
    {
        return \DB::table('staking_wallets')->insertGetId([
            'staking_asset_id' => $assetId,
            'network' => strtolower($symbol),
            'network_environment' => 'testnet',
            'wallet_address' => strtolower($symbol).'-staking-wallet',
            'wallet_type' => 'staking',
            'custody_provider' => 'test-custody',
            'secure_key_reference' => "kms://staking/{$symbol}",
            'status' => 'active',
            'fee_balance' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubmittedDelegationBatch(int $assetId, int $validatorId, int $walletId, int $positionId, string $amount, string $transactionHash): int
    {
        $batchId = \DB::table('staking_delegation_batches')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'staking_asset_id' => $assetId,
            'validator_id' => $validatorId,
            'staking_wallet_id' => $walletId,
            'network_environment' => 'testnet',
            'total_amount' => $amount,
            'position_count' => 1,
            'status' => 'delegation_submitted',
            'unsigned_payload_reference' => "unsigned-{$transactionHash}",
            'signing_request_reference' => "signer-{$transactionHash}",
            'blockchain_transaction_hash' => $transactionHash,
            'submitted_at' => now(),
            'idempotency_key' => "batch-{$transactionHash}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('staking_delegation_allocations')->insert([
            'staking_delegation_batch_id' => $batchId,
            'staking_position_id' => $positionId,
            'allocated_amount' => $amount,
            'status' => 'allocated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('staking_delegations')->insert([
            'staking_asset_id' => $assetId,
            'staking_validator_id' => $validatorId,
            'staking_wallet_id' => $walletId,
            'provider_reference' => "delegation-batch:{$batchId}",
            'delegated_amount' => $amount,
            'active_amount' => '0',
            'blockchain_transaction_hash' => $transactionHash,
            'status' => 'delegation_submitted',
            'delegated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('staking_transactions')->insert([
            'public_id' => (string) Str::uuid(),
            'staking_asset_id' => $assetId,
            'staking_delegation_id' => \DB::table('staking_delegations')->where('provider_reference', "delegation-batch:{$batchId}")->value('id'),
            'transaction_type' => 'delegation',
            'amount' => $amount,
            'fee_amount' => '0',
            'net_amount' => $amount,
            'blockchain_transaction_hash' => $transactionHash,
            'status' => 'submitted',
            'idempotency_key' => "staking:delegation-submitted:{$batchId}",
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $batchId;
    }
}
