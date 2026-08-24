<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiat_currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name', 120);
            $table->unsignedTinyInteger('precision')->default(2);
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdrawal_enabled')->default(false);
            $table->boolean('convert_enabled')->default(false);
            $table->boolean('p2p_enabled')->default(false);
            $table->decimal('minimum_deposit', 36, 18)->default('0');
            $table->decimal('maximum_deposit', 36, 18)->default('0');
            $table->decimal('minimum_withdrawal', 36, 18)->default('0');
            $table->decimal('maximum_withdrawal', 36, 18)->default('0');
            $table->decimal('daily_limit', 36, 18)->default('0');
            $table->string('status', 40)->default('CONFIGURATION_REQUIRED');
            $table->json('requirements')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('account_reference')->unique();
            $table->string('environment')->default('sandbox');
            $table->string('state')->default('CREDENTIALS_REQUIRED');
            $table->json('capabilities')->nullable();
            $table->json('secret_references')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['provider', 'state']);
        });

        Schema::create('payment_provider_health', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('currency', 8)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('state')->default('UNHEALTHY');
            $table->unsignedInteger('latency_ms')->default(0);
            $table->decimal('success_rate', 8, 6)->default('0');
            $table->timestamp('checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'currency', 'country'], 'provider_health_scope_unique');
        });

        Schema::create('bank_directory_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('country', 2);
            $table->string('currency', 8);
            $table->string('bank_code', 64);
            $table->string('bank_name', 160);
            $table->boolean('transfer_supported')->default(false);
            $table->boolean('account_verification_supported')->default(false);
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'country', 'currency', 'bank_code'], 'bank_directory_unique');
        });

        Schema::create('phase10_virtual_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('virtual_account_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 8);
            $table->string('country', 2);
            $table->string('provider');
            $table->string('provider_account_id')->nullable();
            $table->string('account_number');
            $table->string('account_name');
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('reference')->unique();
            $table->string('status')->default('ACTIVE');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'account_number'], 'phase10_virtual_account_unique');
            $table->index(['user_id', 'currency', 'status']);
        });

        Schema::create('provider_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('provider');
            $table->string('event_id')->nullable();
            $table->string('event_type');
            $table->string('status')->default('ACCEPTED');
            $table->string('payload_hash');
            $table->string('signature_status')->default('UNVERIFIED');
            $table->json('normalized_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id'], 'provider_webhook_event_unique');
            $table->index(['provider', 'event_type', 'status']);
        });

        Schema::create('fiat_deposits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('deposit_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('virtual_account_id')->nullable()->constrained('phase10_virtual_accounts')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_transaction_id');
            $table->string('provider_reference')->nullable();
            $table->string('currency', 8);
            $table->decimal('gross_amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('net_amount', 36, 18)->default('0');
            $table->string('sender_name')->nullable();
            $table->string('sender_bank')->nullable();
            $table->string('destination_account')->nullable();
            $table->string('status')->default('DETECTED');
            $table->string('settlement_status')->default('PENDING');
            $table->string('ledger_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id'], 'fiat_deposit_provider_tx_unique');
            $table->index(['user_id', 'currency', 'status']);
        });

        Schema::create('fiat_deposit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiat_deposit_id')->constrained('fiat_deposits')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('correlation_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('bank_account_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('country', 2);
            $table->string('currency', 8);
            $table->string('provider');
            $table->string('bank_code', 64);
            $table->string('bank_name', 160);
            $table->string('account_number', 64);
            $table->string('masked_account_number', 32);
            $table->string('verified_account_name', 160)->nullable();
            $table->string('verification_status')->default('UNVERIFIED');
            $table->string('verification_reference')->nullable();
            $table->boolean('whitelisted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'country', 'currency', 'bank_code', 'account_number'], 'user_bank_account_unique');
        });

        Schema::create('provider_transfer_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('recipient_id')->unique();
            $table->foreignId('user_bank_account_id')->constrained('user_bank_accounts')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_recipient_id');
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'user_bank_account_id'], 'provider_recipient_unique');
        });

        Schema::create('phase10_fiat_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('withdrawal_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_bank_account_id')->constrained('user_bank_accounts')->cascadeOnDelete();
            $table->string('provider');
            $table->string('currency', 8);
            $table->decimal('amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('recipient_receives', 36, 18)->default('0');
            $table->string('status')->default('REQUESTED');
            $table->string('risk_decision')->nullable();
            $table->string('reservation_id')->nullable()->index();
            $table->string('ledger_reference')->nullable()->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->string('idempotency_key');
            $table->json('metadata')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'phase10_fiat_withdrawal_idem');
        });

        Schema::create('fiat_withdrawal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiat_withdrawal_id')->constrained('phase10_fiat_withdrawals')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('correlation_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transfer_id')->unique();
            $table->foreignId('fiat_withdrawal_id')->nullable()->constrained('phase10_fiat_withdrawals')->nullOnDelete();
            $table->string('provider');
            $table->string('currency', 8);
            $table->decimal('amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key');
            $table->string('status')->default('CREATED');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'idempotency_key'], 'provider_transfer_idem');
        });

        Schema::create('provider_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('settlement_uuid')->unique();
            $table->string('provider');
            $table->string('provider_settlement_id');
            $table->string('currency', 8);
            $table->decimal('gross_amount', 36, 18)->default('0');
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('net_amount', 36, 18)->default('0');
            $table->string('destination_bank')->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('status')->default('PENDING');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_settlement_id'], 'provider_settlement_unique');
        });

        Schema::create('fiat_treasury_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('currency', 8);
            $table->string('bucket');
            $table->decimal('available_amount', 36, 18)->default('0');
            $table->decimal('reserved_amount', 36, 18)->default('0');
            $table->decimal('pending_settlement', 36, 18)->default('0');
            $table->string('status')->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['currency', 'bucket']);
        });

        Schema::create('fiat_withdrawal_reserves', function (Blueprint $table): void {
            $table->id();
            $table->string('currency', 8)->unique();
            $table->decimal('pending_withdrawals', 36, 18)->default('0');
            $table->decimal('volume_24h', 36, 18)->default('0');
            $table->decimal('volume_7d', 36, 18)->default('0');
            $table->decimal('provider_balance', 36, 18)->default('0');
            $table->decimal('bank_balance', 36, 18)->default('0');
            $table->decimal('minimum_reserve', 36, 18)->default('0');
            $table->decimal('target_reserve', 36, 18)->default('0');
            $table->decimal('stress_reserve', 36, 18)->default('0');
            $table->string('status')->default('LOW');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fiat_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('currency', 8)->nullable();
            $table->string('status')->default('PASS');
            $table->decimal('user_liabilities', 36, 18)->default('0');
            $table->decimal('controlled_backing', 36, 18)->default('0');
            $table->decimal('coverage_ratio', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fiat_reconciliation_differences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiat_reconciliation_run_id')->constrained('fiat_reconciliation_runs')->cascadeOnDelete();
            $table->string('severity')->default('WARNING');
            $table->string('type');
            $table->string('currency', 8)->nullable();
            $table->decimal('difference_amount', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fiat_daily_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date');
            $table->string('currency', 8);
            $table->decimal('user_liabilities', 36, 18)->default('0');
            $table->decimal('provider_balances', 36, 18)->default('0');
            $table->decimal('settlement_bank_balances', 36, 18)->default('0');
            $table->decimal('pending_deposits', 36, 18)->default('0');
            $table->decimal('pending_withdrawals', 36, 18)->default('0');
            $table->decimal('withdrawal_reserve', 36, 18)->default('0');
            $table->decimal('merchant_liabilities', 36, 18)->default('0');
            $table->decimal('fees', 36, 18)->default('0');
            $table->decimal('difference', 36, 18)->default('0');
            $table->decimal('coverage_ratio', 36, 18)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['snapshot_date', 'currency']);
        });

        Schema::create('payment_disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dispute_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fiat_deposit_id')->nullable()->constrained('fiat_deposits')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_reference');
            $table->string('currency', 8);
            $table->decimal('amount', 36, 18);
            $table->string('status')->default('OPEN');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('refund_id')->unique();
            $table->string('original_reference');
            $table->string('currency', 8);
            $table->decimal('amount', 36, 18);
            $table->string('status')->default('CREATED');
            $table->string('ledger_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('merchant_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_name');
            $table->string('settlement_currency', 8);
            $table->string('status')->default('APPLIED');
            $table->string('risk_status')->default('NORMAL');
            $table->json('profile')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('exaearn_pay_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('pay_intent_id')->unique();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_reference')->unique();
            $table->string('currency', 8);
            $table->decimal('amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->string('description')->nullable();
            $table->string('status')->default('CREATED');
            $table->string('ledger_reference')->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('settlement_id')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('currency', 8);
            $table->decimal('gross_amount', 36, 18)->default('0');
            $table->decimal('refund_amount', 36, 18)->default('0');
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('net_amount', 36, 18)->default('0');
            $table->string('status')->default('PENDING');
            $table->string('ledger_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'merchant_settlements',
            'exaearn_pay_intents',
            'merchants',
            'payment_refunds',
            'payment_disputes',
            'fiat_daily_snapshots',
            'fiat_reconciliation_differences',
            'fiat_reconciliation_runs',
            'fiat_withdrawal_reserves',
            'fiat_treasury_balances',
            'provider_settlements',
            'provider_transfers',
            'fiat_withdrawal_events',
            'phase10_fiat_withdrawals',
            'provider_transfer_recipients',
            'user_bank_accounts',
            'fiat_deposit_events',
            'fiat_deposits',
            'provider_webhook_events',
            'phase10_virtual_accounts',
            'bank_directory_entries',
            'payment_provider_health',
            'payment_provider_accounts',
            'fiat_currencies',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
