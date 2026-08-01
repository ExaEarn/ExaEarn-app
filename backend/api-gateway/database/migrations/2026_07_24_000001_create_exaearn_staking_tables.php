<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staking_assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('symbol', 16);
            $table->string('network', 64);
            $table->string('provider', 120);
            $table->string('staking_type', 64);
            $table->string('readiness_status', 32)->default('development');
            $table->boolean('native_staking_enabled')->default(false);
            $table->boolean('mainnet_enabled')->default(false);
            $table->boolean('testnet_enabled')->default(false);
            $table->boolean('new_positions_enabled')->default(false);
            $table->boolean('unstaking_enabled')->default(false);
            $table->boolean('emergency_paused')->default(true);
            $table->decimal('minimum_stake', 36, 18)->default(0);
            $table->decimal('maximum_stake', 36, 18)->nullable();
            $table->decimal('delegation_minimum', 36, 18)->default(0);
            $table->unsignedSmallInteger('amount_precision')->default(8);
            $table->unsignedSmallInteger('reward_precision')->default(8);
            $table->decimal('platform_commission_rate', 18, 8)->default(0);
            $table->decimal('displayed_apy', 18, 8)->nullable();
            $table->string('reward_distribution_frequency', 32)->default('network_period');
            $table->unsignedBigInteger('expected_activation_seconds')->nullable();
            $table->unsignedBigInteger('unbonding_period_seconds')->nullable();
            $table->unsignedBigInteger('minimum_lock_period_seconds')->nullable();
            $table->boolean('supports_flexible_staking')->default(false);
            $table->boolean('supports_locked_staking')->default(false);
            $table->boolean('supports_partial_unstaking')->default(false);
            $table->boolean('supports_reward_claiming')->default(false);
            $table->boolean('auto_compound_supported')->default(false);
            $table->string('validator_selection_strategy', 64)->default('allowlist_health_weighted');
            $table->string('primary_rpc_reference')->nullable();
            $table->string('secondary_rpc_reference')->nullable();
            $table->unsignedInteger('confirmation_requirement')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['symbol', 'network']);
            $table->index(['readiness_status', 'native_staking_enabled', 'emergency_paused']);
        });

        Schema::create('staking_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_asset_id')->constrained('staking_assets')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('product_type', 32);
            $table->string('status', 32)->default('disabled');
            $table->string('network_environment', 32)->default('testnet');
            $table->unsignedInteger('duration_days')->nullable();
            $table->decimal('minimum_amount', 36, 18)->default(0);
            $table->decimal('maximum_amount', 36, 18)->nullable();
            $table->decimal('displayed_apy', 18, 8)->nullable();
            $table->decimal('platform_commission_rate', 18, 8)->default(0);
            $table->string('reward_schedule', 64)->default('verified_network_rewards');
            $table->string('redemption_type', 32)->default('network_unbonding');
            $table->unsignedBigInteger('unbonding_period_seconds')->nullable();
            $table->boolean('early_redemption_allowed')->default(false);
            $table->decimal('early_redemption_penalty_rate', 18, 8)->default(0);
            $table->boolean('auto_compound_supported')->default(false);
            $table->decimal('capacity', 36, 18)->nullable();
            $table->decimal('total_subscribed', 36, 18)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('terms_version', 32)->default('staking-v1');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('staking_positions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staking_product_id')->constrained('staking_products');
            $table->foreignId('staking_asset_id')->constrained('staking_assets');
            $table->decimal('principal_amount', 36, 18);
            $table->decimal('active_principal_amount', 36, 18)->default(0);
            $table->decimal('pending_stake_amount', 36, 18)->default(0);
            $table->decimal('pending_unstake_amount', 36, 18)->default(0);
            $table->decimal('total_native_gross_rewards', 36, 18)->default(0);
            $table->decimal('total_native_validator_fees', 36, 18)->default(0);
            $table->decimal('total_native_network_fees', 36, 18)->default(0);
            $table->decimal('total_native_platform_fees', 36, 18)->default(0);
            $table->decimal('total_native_net_rewards', 36, 18)->default(0);
            $table->decimal('total_exatoken_bonus_rewards', 36, 18)->default(0);
            $table->decimal('claimed_native_rewards', 36, 18)->default(0);
            $table->decimal('claimed_exatoken_rewards', 36, 18)->default(0);
            $table->string('status', 40)->index();
            $table->boolean('auto_compound_enabled')->default(false);
            $table->timestamp('opened_at');
            $table->timestamp('delegation_submitted_at')->nullable();
            $table->timestamp('activation_at')->nullable();
            $table->timestamp('lock_ends_at')->nullable();
            $table->timestamp('unstaking_requested_at')->nullable();
            $table->timestamp('unbonding_started_at')->nullable();
            $table->timestamp('unbonding_ends_at')->nullable();
            $table->timestamp('withdrawable_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('terms_version', 32);
            $table->string('source', 32)->default('api');
            $table->string('idempotency_key', 120);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('staking_validators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_asset_id')->constrained('staking_assets')->cascadeOnDelete();
            $table->string('provider_identifier');
            $table->string('validator_name');
            $table->string('validator_address');
            $table->string('secondary_identifier')->nullable();
            $table->decimal('commission_rate', 18, 8)->default(0);
            $table->string('status', 32)->default('inactive');
            $table->boolean('preferred')->default(false);
            $table->boolean('allowlisted')->default(false);
            $table->decimal('delegation_capacity', 36, 18)->nullable();
            $table->decimal('delegated_amount', 36, 18)->default(0);
            $table->decimal('minimum_delegation', 36, 18)->nullable();
            $table->decimal('performance_score', 18, 8)->nullable();
            $table->decimal('uptime_percentage', 18, 8)->nullable();
            $table->boolean('jailed_or_delinquent')->default(false);
            $table->jsonb('slashing_history')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['staking_asset_id', 'provider_identifier']);
        });

        Schema::create('staking_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_asset_id')->constrained('staking_assets')->cascadeOnDelete();
            $table->string('network', 64);
            $table->string('network_environment', 32);
            $table->string('wallet_address');
            $table->string('wallet_type', 32);
            $table->string('custody_provider', 64);
            $table->string('secure_key_reference')->nullable();
            $table->string('status', 32)->default('inactive');
            $table->decimal('total_delegated', 36, 18)->default(0);
            $table->decimal('available_balance', 36, 18)->default(0);
            $table->decimal('reserved_balance', 36, 18)->default(0);
            $table->decimal('fee_balance', 36, 18)->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('staking_delegation_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('staking_asset_id')->constrained('staking_assets');
            $table->foreignId('validator_id')->constrained('staking_validators');
            $table->foreignId('staking_wallet_id')->constrained('staking_wallets');
            $table->string('network_environment', 32);
            $table->decimal('total_amount', 36, 18);
            $table->unsignedInteger('position_count');
            $table->string('status', 40);
            $table->string('unsigned_payload_reference')->nullable();
            $table->string('signing_request_reference')->nullable();
            $table->string('blockchain_transaction_hash')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('staking_delegation_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_delegation_batch_id')->constrained('staking_delegation_batches')->cascadeOnDelete();
            $table->foreignId('staking_position_id')->constrained('staking_positions')->cascadeOnDelete();
            $table->decimal('allocated_amount', 36, 18);
            $table->decimal('activated_amount', 36, 18)->default(0);
            $table->string('status', 40);
            $table->timestamps();
        });

        Schema::create('staking_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_asset_id')->constrained('staking_assets');
            $table->foreignId('staking_validator_id')->constrained('staking_validators');
            $table->foreignId('staking_wallet_id')->constrained('staking_wallets');
            $table->string('provider_reference')->unique();
            $table->string('blockchain_stake_reference')->nullable();
            $table->decimal('delegated_amount', 36, 18);
            $table->decimal('active_amount', 36, 18)->default(0);
            $table->decimal('pending_undelegation_amount', 36, 18)->default(0);
            $table->string('blockchain_transaction_hash')->nullable();
            $table->string('status', 40);
            $table->timestamp('delegated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('undelegated_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('staking_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staking_position_id')->nullable()->constrained('staking_positions')->nullOnDelete();
            $table->foreignId('staking_asset_id')->constrained('staking_assets');
            $table->foreignId('staking_delegation_id')->nullable()->constrained('staking_delegations')->nullOnDelete();
            $table->string('transaction_type', 64);
            $table->unsignedBigInteger('reward_asset_id')->nullable();
            $table->decimal('amount', 36, 18)->default(0);
            $table->decimal('fee_amount', 36, 18)->default(0);
            $table->decimal('net_amount', 36, 18)->default(0);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('blockchain_transaction_hash')->nullable();
            $table->string('blockchain_block_or_slot')->nullable();
            $table->unsignedInteger('confirmation_count')->nullable();
            $table->string('status', 40);
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staking_reward_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_asset_id')->constrained('staking_assets');
            $table->foreignId('staking_validator_id')->nullable()->constrained('staking_validators')->nullOnDelete();
            $table->foreignId('staking_delegation_id')->nullable()->constrained('staking_delegations')->nullOnDelete();
            $table->string('network_environment', 32);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('gross_native_reward', 36, 18);
            $table->decimal('validator_fee', 36, 18)->default(0);
            $table->decimal('network_cost', 36, 18)->default(0);
            $table->decimal('provider_fee', 36, 18)->default(0);
            $table->decimal('platform_commission', 36, 18)->default(0);
            $table->decimal('distributable_native_reward', 36, 18)->default(0);
            $table->string('blockchain_reference');
            $table->string('provider_reference')->nullable();
            $table->string('status', 40);
            $table->string('calculation_hash', 128);
            $table->string('source_data_hash', 128);
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['staking_asset_id', 'blockchain_reference']);
        });

        Schema::create('staking_reward_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staking_reward_batch_id')->constrained('staking_reward_batches')->cascadeOnDelete();
            $table->foreignId('staking_position_id')->constrained('staking_positions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('eligible_principal', 36, 18);
            $table->decimal('eligible_seconds_or_epochs', 36, 18);
            $table->decimal('weighted_share', 36, 18);
            $table->decimal('gross_native_reward', 36, 18);
            $table->decimal('validator_fee_share', 36, 18)->default(0);
            $table->decimal('network_fee_share', 36, 18)->default(0);
            $table->decimal('platform_fee', 36, 18)->default(0);
            $table->decimal('net_native_reward', 36, 18);
            $table->decimal('exatoken_bonus_amount', 36, 18)->default(0);
            $table->string('status', 40);
            $table->unsignedBigInteger('native_reward_ledger_transaction_id')->nullable();
            $table->unsignedBigInteger('exatoken_reward_ledger_transaction_id')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();
            $table->unique(['staking_reward_batch_id', 'staking_position_id']);
        });

        Schema::create('staking_unstake_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('staking_position_id')->constrained('staking_positions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_amount', 36, 18);
            $table->string('status', 40);
            $table->timestamp('requested_at');
            $table->timestamp('undelegation_submitted_at')->nullable();
            $table->timestamp('unbonding_started_at')->nullable();
            $table->timestamp('expected_completion_at')->nullable();
            $table->timestamp('confirmed_withdrawable_at')->nullable();
            $table->timestamp('principal_released_at')->nullable();
            $table->string('blockchain_transaction_hash')->nullable();
            $table->string('idempotency_key', 120);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('exatoken_staking_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default('draft');
            $table->decimal('budget_amount', 36, 18);
            $table->decimal('reserved_amount', 36, 18)->default(0);
            $table->decimal('distributed_amount', 36, 18)->default(0);
            $table->decimal('per_user_cap', 36, 18)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('eligibility_rules')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('exatoken_reward_reserve_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_type', 40);
            $table->decimal('amount', 36, 18);
            $table->decimal('balance_after', 36, 18);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('reference')->unique();
            $table->string('status', 40);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('exatoken_reward_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exatoken_staking_campaign_id')->constrained('exatoken_staking_campaigns');
            $table->foreignId('staking_position_id')->nullable()->constrained('staking_positions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 36, 18);
            $table->string('status', 40);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('distributed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('exatoken_campaign_eligibility_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exatoken_staking_campaign_id')->constrained('exatoken_staking_campaigns');
            $table->foreignId('staking_position_id')->nullable()->constrained('staking_positions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        foreach ([
            'staking_slashing_events',
            'staking_apy_history',
            'staking_terms_acceptances',
            'staking_audit_logs',
            'staking_reconciliation_reports',
            'staking_reconciliation_differences',
            'staking_provider_health_checks',
            'staking_network_statuses',
            'staking_rpc_health_checks',
            'staking_validator_health_history',
        ] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->foreignId('staking_asset_id')->nullable()->constrained('staking_assets')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('status', 40)->default('recorded');
                $table->decimal('amount', 36, 18)->nullable();
                $table->string('reference')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
                if ($name === 'staking_terms_acceptances') {
                    $table->string('terms_version', 32)->default('staking-v1');
                    $table->unique(['user_id', 'terms_version']);
                }
            });
        }

        $this->seedAssets();
    }

    public function down(): void
    {
        foreach ([
            'staking_validator_health_history',
            'staking_rpc_health_checks',
            'staking_network_statuses',
            'staking_provider_health_checks',
            'staking_reconciliation_differences',
            'staking_reconciliation_reports',
            'staking_audit_logs',
            'staking_terms_acceptances',
            'staking_apy_history',
            'staking_slashing_events',
            'exatoken_campaign_eligibility_records',
            'exatoken_reward_allocations',
            'exatoken_reward_reserve_transactions',
            'exatoken_staking_campaigns',
            'staking_unstake_requests',
            'staking_reward_allocations',
            'staking_reward_batches',
            'staking_transactions',
            'staking_delegations',
            'staking_delegation_allocations',
            'staking_delegation_batches',
            'staking_wallets',
            'staking_validators',
            'staking_positions',
            'staking_products',
            'staking_assets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function seedAssets(): void
    {
        $now = now();
        $assets = [
            ['SOL', 'solana', 'SolanaStakingProvider', 'delegated_stake', 9],
            ['ETH', 'ethereum', 'EthereumStakingProvider', 'validator_or_pool', 18],
            ['ADA', 'cardano', 'CardanoStakingProvider', 'stake_pool_delegation', 6],
            ['BNB', 'bnb-smart-chain', 'BnbStakingProvider', 'validator_delegation', 18],
            ['AVAX', 'avalanche', 'AvalancheStakingProvider', 'fixed_duration_delegation', 18],
            ['SUI', 'sui', 'SuiStakingProvider', 'validator_pool_object', 9],
            ['DOT', 'polkadot', 'PolkadotStakingProvider', 'nomination_pool', 10],
            ['ATOM', 'cosmos-hub', 'CosmosStakingProvider', 'validator_delegation', 6],
            ['NEAR', 'near', 'NearStakingProvider', 'staking_pool_contract', 24],
            ['XTZ', 'tezos', 'TezosStakingProvider', 'baker_delegation', 6],
            ['POL', 'polygon', 'PolygonStakingProvider', 'stake_manager_contract', 18],
        ];

        foreach ($assets as [$symbol, $network, $provider, $type, $precision]) {
            DB::table('staking_assets')->updateOrInsert(
                ['symbol' => $symbol, 'network' => $network],
                [
                    'provider' => $provider,
                    'staking_type' => $type,
                    'readiness_status' => 'development',
                    'native_staking_enabled' => false,
                    'mainnet_enabled' => false,
                    'testnet_enabled' => false,
                    'new_positions_enabled' => false,
                    'unstaking_enabled' => false,
                    'emergency_paused' => true,
                    'minimum_stake' => '0',
                    'delegation_minimum' => '0',
                    'amount_precision' => $precision,
                    'reward_precision' => $precision,
                    'platform_commission_rate' => '0',
                    'reward_distribution_frequency' => 'network_period',
                    'supports_flexible_staking' => false,
                    'supports_locked_staking' => true,
                    'supports_partial_unstaking' => true,
                    'supports_reward_claiming' => true,
                    'auto_compound_supported' => false,
                    'validator_selection_strategy' => 'allowlist_health_weighted',
                    'confirmation_requirement' => 1,
                    'metadata' => json_encode(['mainnet_activation_requires_dual_approval' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
