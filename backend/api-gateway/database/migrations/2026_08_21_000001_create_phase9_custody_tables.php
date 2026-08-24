<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_networks', function (Blueprint $table): void {
            $table->id();
            $table->string('network')->unique();
            $table->string('family');
            $table->unsignedBigInteger('chain_id')->nullable();
            $table->string('native_asset');
            $table->string('state')->default('DEGRADED');
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdrawal_enabled')->default(false);
            $table->unsignedInteger('required_confirmations')->default(1);
            $table->unsignedInteger('finality_confirmations')->default(1);
            $table->boolean('memo_required')->default(false);
            $table->json('policy')->nullable();
            $table->timestamp('last_health_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blockchain_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blockchain_network_id')->constrained('blockchain_networks')->cascadeOnDelete();
            $table->string('asset');
            $table->string('network');
            $table->string('asset_type');
            $table->string('contract_address')->nullable();
            $table->unsignedTinyInteger('decimals');
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdrawal_enabled')->default(false);
            $table->decimal('minimum_deposit', 36, 18)->default('0');
            $table->decimal('minimum_withdrawal', 36, 18)->default('0');
            $table->decimal('maximum_withdrawal', 36, 18)->default('0');
            $table->unsignedInteger('required_confirmations')->default(1);
            $table->decimal('sweep_threshold', 36, 18)->default('0');
            $table->decimal('rebalance_threshold', 36, 18)->default('0');
            $table->json('fee_policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['asset', 'network', 'contract_address'], 'blockchain_assets_unique');
            $table->index(['network', 'asset']);
        });

        Schema::create('blockchain_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_id')->unique();
            $table->string('network');
            $table->string('name');
            $table->string('type')->default('rpc');
            $table->string('state')->default('OFFLINE');
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('requests_this_minute')->default(0);
            $table->timestamp('quota_resets_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['network', 'state', 'priority']);
        });

        Schema::create('custody_wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->string('classification');
            $table->string('network');
            $table->string('asset')->nullable();
            $table->string('address')->nullable();
            $table->string('label')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->decimal('target_balance', 36, 18)->default('0');
            $table->decimal('minimum_balance', 36, 18)->default('0');
            $table->decimal('maximum_balance', 36, 18)->default('0');
            $table->json('policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['network', 'asset', 'classification', 'status']);
        });

        Schema::create('custody_addresses', function (Blueprint $table): void {
            $table->id();
            $table->string('address_id')->unique();
            $table->foreignId('custody_wallet_id')->nullable()->constrained('custody_wallets')->nullOnDelete();
            $table->string('network');
            $table->string('address');
            $table->string('memo_tag')->nullable();
            $table->string('address_type')->default('USER_DEPOSIT');
            $table->string('derivation_reference')->nullable();
            $table->unsignedBigInteger('derivation_index')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'address', 'memo_tag'], 'custody_address_identity');
        });

        Schema::create('custody_address_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_address_id')->constrained('custody_addresses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset');
            $table->string('network');
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'asset', 'network'], 'custody_assignment_user_asset_network');
            $table->index(['network', 'asset', 'status']);
        });

        Schema::create('custody_deposits', function (Blueprint $table): void {
            $table->id();
            $table->string('deposit_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('custody_address_id')->nullable()->constrained('custody_addresses')->nullOnDelete();
            $table->string('network');
            $table->string('asset');
            $table->string('tx_hash');
            $table->string('event_identifier');
            $table->unsignedBigInteger('block_height')->nullable();
            $table->string('block_hash')->nullable();
            $table->string('sender')->nullable();
            $table->string('destination');
            $table->string('memo_tag')->nullable();
            $table->decimal('amount', 36, 18);
            $table->unsignedInteger('confirmations')->default(0);
            $table->unsignedInteger('required_confirmations')->default(1);
            $table->string('detection_source')->default('scanner');
            $table->string('status')->default('DETECTED');
            $table->string('ledger_reference')->nullable()->unique();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'tx_hash', 'event_identifier'], 'custody_deposit_chain_identity');
            $table->index(['network', 'asset', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('custody_deposit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_deposit_id')->constrained('custody_deposits')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('correlation_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('custody_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->string('withdrawal_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset');
            $table->string('network');
            $table->decimal('amount', 36, 18);
            $table->decimal('network_fee', 36, 18)->default('0');
            $table->decimal('platform_fee', 36, 18)->default('0');
            $table->string('destination_address');
            $table->string('memo_tag')->nullable();
            $table->string('status')->default('REQUESTED');
            $table->string('risk_decision')->nullable();
            $table->string('reservation_id')->nullable()->index();
            $table->string('ledger_reference')->nullable()->unique();
            $table->string('tx_hash')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('broadcasted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'custody_withdrawal_user_idem');
            $table->index(['network', 'asset', 'status']);
        });

        Schema::create('custody_withdrawal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_withdrawal_id')->constrained('custody_withdrawals')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('correlation_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('custody_signing_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('signing_request_id')->unique();
            $table->foreignId('custody_withdrawal_id')->nullable()->constrained('custody_withdrawals')->nullOnDelete();
            $table->string('provider');
            $table->string('network');
            $table->string('status')->default('REQUESTED');
            $table->string('request_hash')->unique();
            $table->json('unsigned_payload')->nullable();
            $table->json('signed_payload_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('custody_broadcast_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_withdrawal_id')->constrained('custody_withdrawals')->cascadeOnDelete();
            $table->string('network');
            $table->string('provider');
            $table->string('status')->default('SUBMITTED');
            $table->string('tx_hash')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['network', 'status']);
        });

        Schema::create('custody_transaction_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->string('network');
            $table->string('tx_hash');
            $table->unsignedBigInteger('block_height')->nullable();
            $table->string('block_hash')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status')->default('PENDING');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'tx_hash']);
        });

        Schema::create('custody_sweeps', function (Blueprint $table): void {
            $table->id();
            $table->string('sweep_id')->unique();
            $table->string('network');
            $table->string('asset');
            $table->foreignId('from_custody_wallet_id')->nullable()->constrained('custody_wallets')->nullOnDelete();
            $table->foreignId('to_custody_wallet_id')->nullable()->constrained('custody_wallets')->nullOnDelete();
            $table->decimal('amount', 36, 18);
            $table->string('action');
            $table->string('status')->default('PLANNED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('custody_network_fee_reserves', function (Blueprint $table): void {
            $table->id();
            $table->string('network');
            $table->string('asset');
            $table->decimal('available_amount', 36, 18)->default('0');
            $table->decimal('reserved_amount', 36, 18)->default('0');
            $table->decimal('minimum_amount', 36, 18)->default('0');
            $table->string('status')->default('LOW');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'asset']);
        });

        Schema::create('custody_wallet_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_wallet_id')->nullable()->constrained('custody_wallets')->nullOnDelete();
            $table->string('network');
            $table->string('asset');
            $table->decimal('balance', 36, 18)->default('0');
            $table->string('source')->default('provider');
            $table->timestamp('observed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['network', 'asset', 'observed_at']);
        });

        Schema::create('bitcoin_utxos', function (Blueprint $table): void {
            $table->id();
            $table->string('network')->default('bitcoin');
            $table->string('tx_hash');
            $table->unsignedInteger('output_index');
            $table->string('address');
            $table->decimal('amount', 36, 18);
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('spend_status')->default('UNSPENT');
            $table->string('reservation_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'tx_hash', 'output_index']);
            $table->index(['address', 'spend_status']);
        });

        Schema::create('blockchain_nonce_states', function (Blueprint $table): void {
            $table->id();
            $table->string('network');
            $table->string('address');
            $table->unsignedBigInteger('next_nonce')->default(0);
            $table->string('status')->default('READY');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'address']);
        });

        Schema::create('custody_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_id')->unique();
            $table->string('asset')->nullable();
            $table->string('network')->nullable();
            $table->string('status')->default('RUNNING');
            $table->decimal('user_liabilities', 36, 18)->default('0');
            $table->decimal('controlled_backing', 36, 18)->default('0');
            $table->decimal('coverage_ratio', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('custody_reconciliation_differences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custody_reconciliation_run_id')->constrained('custody_reconciliation_runs')->cascadeOnDelete();
            $table->string('severity')->default('INFO');
            $table->string('type');
            $table->string('asset')->nullable();
            $table->string('network')->nullable();
            $table->decimal('difference_amount', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('custody_daily_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date');
            $table->string('asset');
            $table->string('network')->nullable();
            $table->decimal('user_liabilities', 36, 18)->default('0');
            $table->decimal('controlled_backing', 36, 18)->default('0');
            $table->decimal('hot_balance', 36, 18)->default('0');
            $table->decimal('cold_balance', 36, 18)->default('0');
            $table->decimal('external_balance', 36, 18)->default('0');
            $table->decimal('staked_balance', 36, 18)->default('0');
            $table->decimal('pending_deposits', 36, 18)->default('0');
            $table->decimal('pending_withdrawals', 36, 18)->default('0');
            $table->decimal('network_fee_reserve', 36, 18)->default('0');
            $table->decimal('difference', 36, 18)->default('0');
            $table->decimal('coverage_ratio', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['snapshot_date', 'asset', 'network']);
        });

        Schema::create('custody_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('approval_id')->unique();
            $table->string('action_type');
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('asset')->nullable();
            $table->string('network')->nullable();
            $table->decimal('amount', 36, 18)->nullable();
            $table->string('status')->default('REQUESTED');
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'custody_approval_requests',
            'custody_daily_snapshots',
            'custody_reconciliation_differences',
            'custody_reconciliation_runs',
            'blockchain_nonce_states',
            'bitcoin_utxos',
            'custody_wallet_balance_snapshots',
            'custody_network_fee_reserves',
            'custody_sweeps',
            'custody_transaction_confirmations',
            'custody_broadcast_attempts',
            'custody_signing_requests',
            'custody_withdrawal_events',
            'custody_withdrawals',
            'custody_deposit_events',
            'custody_deposits',
            'custody_address_assignments',
            'custody_addresses',
            'custody_wallets',
            'blockchain_providers',
            'blockchain_assets',
            'blockchain_networks',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
