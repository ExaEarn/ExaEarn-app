<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otc_market_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('config_uuid')->unique();
            $table->string('symbol', 48)->unique();
            $table->string('product_type', 40)->default('CRYPTO_CRYPTO');
            $table->string('base_asset', 24);
            $table->string('quote_asset', 24);
            $table->boolean('enabled')->default(false);
            $table->decimal('minimum_size', 36, 18)->default('0');
            $table->decimal('maximum_size', 36, 18)->default('0');
            $table->unsignedInteger('quote_ttl_seconds')->default(30);
            $table->json('allowed_account_types')->nullable();
            $table->json('allowed_jurisdictions')->nullable();
            $table->json('eligible_liquidity_sources')->nullable();
            $table->decimal('max_spread_bps', 18, 8)->default('100');
            $table->decimal('manual_review_threshold', 36, 18)->default('0');
            $table->string('settlement_mode', 40)->default('INTERNAL_LEDGER');
            $table->string('partial_fill_policy', 40)->default('ALL_OR_NOTHING');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_liquidity_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('provider_uuid')->unique();
            $table->string('provider_type', 40);
            $table->foreignId('market_maker_id')->nullable()->constrained('market_maker_profiles')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('status', 40)->default('ACTIVE');
            $table->json('capabilities')->nullable();
            $table->json('markets')->nullable();
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['provider_type', 'status']);
        });

        Schema::create('otc_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rfq_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->foreignId('otc_market_config_id')->nullable()->constrained('otc_market_configs')->nullOnDelete();
            $table->string('symbol', 48);
            $table->string('side', 12);
            $table->string('base_asset', 24);
            $table->string('quote_asset', 24);
            $table->decimal('base_amount', 36, 18)->nullable();
            $table->decimal('quote_amount', 36, 18)->nullable();
            $table->string('settlement_asset', 24);
            $table->decimal('settlement_amount', 36, 18);
            $table->string('status', 40)->default('REQUESTED');
            $table->string('execution_preference', 40)->default('ALL_OR_NOTHING');
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->json('eligibility_snapshot')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'status']);
            $table->index(['symbol', 'status']);
        });

        Schema::create('otc_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('quote_uuid')->unique();
            $table->foreignId('rfq_id')->constrained('otc_rfqs')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('otc_liquidity_providers')->nullOnDelete();
            $table->string('quote_type', 24)->default('FIRM');
            $table->string('status', 40)->default('SUBMITTED');
            $table->decimal('price', 36, 18);
            $table->decimal('available_base_amount', 36, 18);
            $table->decimal('minimum_base_amount', 36, 18)->default('0');
            $table->decimal('provider_fee', 36, 18)->default('0');
            $table->string('fee_asset', 24)->nullable();
            $table->decimal('client_price', 36, 18)->nullable();
            $table->decimal('client_fee', 36, 18)->default('0');
            $table->string('client_fee_asset', 24)->nullable();
            $table->timestamp('valid_until');
            $table->string('provider_reference', 160)->nullable();
            $table->json('validation_snapshot')->nullable();
            $table->json('best_execution_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['rfq_id', 'status']);
        });

        Schema::create('otc_trades', function (Blueprint $table): void {
            $table->id();
            $table->uuid('trade_uuid')->unique();
            $table->foreignId('rfq_id')->constrained('otc_rfqs')->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('otc_quotes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('symbol', 48);
            $table->string('side', 12);
            $table->decimal('price', 36, 18);
            $table->decimal('base_amount', 36, 18);
            $table->decimal('quote_amount', 36, 18);
            $table->decimal('client_fee', 36, 18)->default('0');
            $table->string('fee_asset', 24);
            $table->string('status', 40)->default('EXECUTING');
            $table->uuid('reservation_id')->nullable();
            $table->string('ledger_reference')->nullable()->unique();
            $table->string('idempotency_key', 160)->unique();
            $table->json('accounting_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_execution_legs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('leg_uuid')->unique();
            $table->foreignId('trade_id')->constrained('otc_trades')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('otc_liquidity_providers')->nullOnDelete();
            $table->string('provider_type', 40);
            $table->string('status', 40)->default('PENDING');
            $table->decimal('price', 36, 18);
            $table->decimal('base_amount', 36, 18);
            $table->decimal('quote_amount', 36, 18);
            $table->string('settlement_mode', 40)->default('INTERNAL_LEDGER');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('settlement_uuid')->unique();
            $table->foreignId('trade_id')->constrained('otc_trades')->cascadeOnDelete();
            $table->string('settlement_type', 40)->default('INTERNAL_MM');
            $table->string('status', 40)->default('PENDING');
            $table->string('ledger_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_counterparty_exposures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('otc_liquidity_providers')->cascadeOnDelete();
            $table->string('asset', 24);
            $table->decimal('credit_limit', 36, 18)->default('0');
            $table->decimal('settlement_limit', 36, 18)->default('0');
            $table->decimal('outstanding_receivable', 36, 18)->default('0');
            $table->decimal('outstanding_payable', 36, 18)->default('0');
            $table->decimal('unsettled_notional', 36, 18)->default('0');
            $table->string('rating', 40)->default('UNRATED');
            $table->string('status', 40)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'asset']);
        });

        Schema::create('otc_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('status', 40);
            $table->unsignedInteger('break_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_risk_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('rfq_id')->nullable()->constrained('otc_rfqs')->nullOnDelete();
            $table->foreignId('trade_id')->nullable()->constrained('otc_trades')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('severity', 24)->default('WARNING');
            $table->string('status', 40)->default('OPEN');
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('otc_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('audit_uuid')->unique();
            $table->foreignId('rfq_id')->nullable()->constrained('otc_rfqs')->nullOnDelete();
            $table->foreignId('trade_id')->nullable()->constrained('otc_trades')->nullOnDelete();
            $table->string('actor_type', 40)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otc_audit_logs');
        Schema::dropIfExists('otc_risk_events');
        Schema::dropIfExists('otc_reconciliation_runs');
        Schema::dropIfExists('otc_counterparty_exposures');
        Schema::dropIfExists('otc_settlements');
        Schema::dropIfExists('otc_execution_legs');
        Schema::dropIfExists('otc_trades');
        Schema::dropIfExists('otc_quotes');
        Schema::dropIfExists('otc_rfqs');
        Schema::dropIfExists('otc_liquidity_providers');
        Schema::dropIfExists('otc_market_configs');
    }
};
