<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('liquidity_sources')) {
            Schema::create('liquidity_sources', function (Blueprint $table): void {
                $table->id();
                $table->uuid('source_id')->unique();
                $table->string('code', 64)->unique();
                $table->string('name', 120);
                $table->string('type', 40);
                $table->string('state', 40)->default('UNCONFIGURED');
                $table->json('capabilities')->nullable();
                $table->json('configuration')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_health_at')->nullable();
                $table->timestamps();
                $table->index(['type', 'state']);
            });
        }

        if (! Schema::hasTable('liquidity_source_markets')) {
            Schema::create('liquidity_source_markets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('liquidity_source_id')->constrained('liquidity_sources')->cascadeOnDelete();
                $table->string('market_symbol', 48);
                $table->string('venue_symbol', 64)->nullable();
                $table->string('status', 40)->default('DISABLED');
                $table->decimal('min_notional', 36, 18)->nullable();
                $table->decimal('min_quantity', 36, 18)->nullable();
                $table->unsignedInteger('price_precision')->nullable();
                $table->unsignedInteger('quantity_precision')->nullable();
                $table->json('fees')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['liquidity_source_id', 'market_symbol'], 'liquidity_source_market_unique');
                $table->index(['market_symbol', 'status']);
            });
        }

        if (! Schema::hasTable('liquidity_source_health')) {
            Schema::create('liquidity_source_health', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('liquidity_source_id')->constrained('liquidity_sources')->cascadeOnDelete();
                $table->string('market_symbol', 48)->nullable();
                $table->string('status', 40);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->decimal('rejection_rate_bps', 18, 8)->default('0');
                $table->string('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();
                $table->unique(['liquidity_source_id', 'market_symbol'], 'liquidity_source_health_unique');
                $table->index(['status', 'checked_at']);
            });
        }

        if (! Schema::hasTable('external_venue_accounts')) {
            Schema::create('external_venue_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('liquidity_source_id')->constrained('liquidity_sources')->cascadeOnDelete();
                $table->string('venue', 64);
                $table->string('account_reference', 120);
                $table->string('environment', 40)->default('UNCONFIGURED');
                $table->string('state', 40)->default('CREDENTIALS_REQUIRED');
                $table->string('api_key_ref')->nullable();
                $table->string('secret_ref')->nullable();
                $table->boolean('trading_enabled')->default(false);
                $table->boolean('withdrawals_enabled')->default(false);
                $table->json('permissions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['venue', 'account_reference']);
            });
        }

        if (! Schema::hasTable('external_venue_balances')) {
            Schema::create('external_venue_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('external_venue_account_id')->constrained('external_venue_accounts')->cascadeOnDelete();
                $table->string('asset', 24);
                $table->decimal('available', 36, 18)->default('0');
                $table->decimal('locked', 36, 18)->default('0');
                $table->decimal('pending_settlement', 36, 18)->default('0');
                $table->decimal('reserved_for_routing', 36, 18)->default('0');
                $table->decimal('operational_minimum', 36, 18)->default('0');
                $table->string('status', 40)->default('UNKNOWN');
                $table->timestamp('synced_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['external_venue_account_id', 'asset'], 'external_venue_balance_unique');
            });
        }

        if (! Schema::hasTable('external_orders')) {
            Schema::create('external_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('external_execution_id')->unique();
                $table->uuid('route_execution_id')->nullable();
                $table->string('parent_reference', 120)->nullable();
                $table->foreignId('external_venue_account_id')->nullable()->constrained('external_venue_accounts')->nullOnDelete();
                $table->string('venue', 64);
                $table->string('venue_order_id')->nullable();
                $table->string('client_order_id')->unique();
                $table->string('market_symbol', 48);
                $table->string('venue_symbol', 64);
                $table->string('side', 12);
                $table->string('type', 32);
                $table->decimal('quantity', 36, 18);
                $table->decimal('price', 36, 18)->nullable();
                $table->decimal('filled_quantity', 36, 18)->default('0');
                $table->decimal('average_fill_price', 36, 18)->default('0');
                $table->decimal('fee', 36, 18)->default('0');
                $table->string('fee_asset', 24)->nullable();
                $table->string('status', 40)->default('CREATED');
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->string('last_error')->nullable();
                $table->string('correlation_id', 120)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
                $table->index(['venue', 'market_symbol', 'status']);
                $table->index(['route_execution_id', 'status']);
            });
        }

        if (! Schema::hasTable('external_fills')) {
            Schema::create('external_fills', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('external_order_id')->constrained('external_orders')->cascadeOnDelete();
                $table->string('venue', 64);
                $table->string('venue_trade_id');
                $table->string('market_symbol', 48);
                $table->decimal('price', 36, 18);
                $table->decimal('quantity', 36, 18);
                $table->decimal('quote_quantity', 36, 18);
                $table->decimal('fee', 36, 18)->default('0');
                $table->string('fee_asset', 24)->nullable();
                $table->string('settlement_reference')->nullable()->unique();
                $table->string('status', 40)->default('RECORDED');
                $table->timestamp('filled_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['venue', 'venue_trade_id']);
                $table->index(['market_symbol', 'filled_at']);
            });
        }

        if (! Schema::hasTable('liquidity_route_plans')) {
            Schema::create('liquidity_route_plans', function (Blueprint $table): void {
                $table->id();
                $table->uuid('route_plan_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('parent_reference', 120);
                $table->string('idempotency_key', 160);
                $table->string('market_symbol', 48);
                $table->string('side', 12);
                $table->string('order_type', 32)->default('market');
                $table->decimal('requested_quantity', 36, 18);
                $table->decimal('limit_price', 36, 18)->nullable();
                $table->string('routing_mode', 64);
                $table->decimal('expected_average_price', 36, 18)->nullable();
                $table->decimal('expected_total_cost', 36, 18)->nullable();
                $table->decimal('quality_score', 18, 8)->default('0');
                $table->string('status', 40)->default('ROUTE_PLANNED');
                $table->json('sources_considered')->nullable();
                $table->json('plan')->nullable();
                $table->json('rejections')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['parent_reference', 'idempotency_key'], 'liquidity_route_plan_idempotency_unique');
                $table->index(['market_symbol', 'status']);
            });
        }

        if (! Schema::hasTable('liquidity_route_executions')) {
            Schema::create('liquidity_route_executions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('route_execution_id')->unique();
                $table->foreignId('liquidity_route_plan_id')->constrained('liquidity_route_plans')->cascadeOnDelete();
                $table->string('source_code', 64);
                $table->string('source_type', 40);
                $table->string('venue_order_id')->nullable();
                $table->string('client_order_id')->nullable()->unique();
                $table->decimal('planned_quantity', 36, 18);
                $table->decimal('planned_price', 36, 18)->nullable();
                $table->decimal('filled_quantity', 36, 18)->default('0');
                $table->decimal('average_fill_price', 36, 18)->default('0');
                $table->decimal('fee', 36, 18)->default('0');
                $table->string('fee_asset', 24)->nullable();
                $table->string('status', 40)->default('CREATED');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['source_code', 'status']);
            });
        }

        if (! Schema::hasTable('liquidity_reservations')) {
            Schema::create('liquidity_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('reservation_id')->unique();
                $table->string('scope', 40);
                $table->string('source_code', 64);
                $table->string('asset', 24);
                $table->decimal('amount', 36, 18);
                $table->decimal('remaining_amount', 36, 18);
                $table->string('purpose', 64);
                $table->string('reference_type', 64);
                $table->string('reference_id', 120);
                $table->string('idempotency_key', 160);
                $table->string('status', 40)->default('ACTIVE');
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['source_code', 'idempotency_key']);
                $table->index(['source_code', 'asset', 'status']);
            });
        }

        if (! Schema::hasTable('treasury_liquidity_buckets')) {
            Schema::create('treasury_liquidity_buckets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('bucket_id')->unique();
                $table->string('asset', 24);
                $table->string('bucket', 64);
                $table->decimal('allocated_amount', 36, 18)->default('0');
                $table->decimal('reserved_amount', 36, 18)->default('0');
                $table->string('status', 40)->default('ACTIVE');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['asset', 'bucket']);
            });
        }

        if (! Schema::hasTable('withdrawal_liquidity_reserves')) {
            Schema::create('withdrawal_liquidity_reserves', function (Blueprint $table): void {
                $table->id();
                $table->uuid('reserve_id')->unique();
                $table->string('asset', 24)->unique();
                $table->decimal('minimum_reserve', 36, 18)->default('0');
                $table->decimal('target_reserve', 36, 18)->default('0');
                $table->decimal('stress_reserve', 36, 18)->default('0');
                $table->decimal('pending_withdrawals', 36, 18)->default('0');
                $table->decimal('available_operational_liquidity', 36, 18)->default('0');
                $table->string('formula_version', 64);
                $table->string('status', 40)->default('UNKNOWN');
                $table->json('metadata')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('treasury_rebalancing_runs')) {
            Schema::create('treasury_rebalancing_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('asset', 24);
                $table->string('status', 40)->default('NO_ACTION');
                $table->json('actions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('evaluated_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('net_exposure_snapshots')) {
            Schema::create('net_exposure_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('snapshot_id')->unique();
                $table->string('asset', 24);
                $table->decimal('user_liability', 36, 18)->default('0');
                $table->decimal('treasury_assets', 36, 18)->default('0');
                $table->decimal('external_venue_exposure', 36, 18)->default('0');
                $table->decimal('reserved_withdrawal_liquidity', 36, 18)->default('0');
                $table->decimal('net_exposure', 36, 18)->default('0');
                $table->decimal('coverage_ratio', 36, 18)->default('0');
                $table->string('status', 40)->default('UNKNOWN');
                $table->json('metadata')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();
                $table->index(['asset', 'calculated_at']);
            });
        }

        if (! Schema::hasTable('market_maker_accounts')) {
            Schema::create('market_maker_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('market_maker_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('account_type', 40)->default('TREASURY');
                $table->string('status', 40)->default('PENDING');
                $table->json('permissions')->nullable();
                $table->json('limits')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('market_maker_quotes')) {
            Schema::create('market_maker_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('quote_id')->unique();
                $table->foreignId('market_maker_account_id')->nullable()->constrained('market_maker_accounts')->nullOnDelete();
                $table->string('market_symbol', 48);
                $table->string('side', 12);
                $table->decimal('price', 36, 18);
                $table->decimal('quantity', 36, 18);
                $table->decimal('reserved_inventory', 36, 18)->default('0');
                $table->string('status', 40)->default('ACTIVE');
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['market_symbol', 'side', 'status']);
            });
        }

        if (! Schema::hasTable('liquidity_reconciliation_runs')) {
            Schema::create('liquidity_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('status', 40);
                $table->unsignedInteger('differences_count')->default(0);
                $table->json('summary')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('liquidity_reconciliation_differences')) {
            Schema::create('liquidity_reconciliation_differences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('liquidity_reconciliation_run_id')->constrained('liquidity_reconciliation_runs')->cascadeOnDelete();
                $table->string('scope', 64);
                $table->string('severity', 32);
                $table->string('code', 96);
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('best_execution_audits')) {
            Schema::create('best_execution_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('audit_id')->unique();
                $table->uuid('route_plan_id')->nullable();
                $table->string('parent_reference', 120);
                $table->string('market_symbol', 48);
                $table->string('side', 12);
                $table->decimal('requested_quantity', 36, 18);
                $table->json('market_state');
                $table->json('sources_considered');
                $table->json('route_selected');
                $table->json('result')->nullable();
                $table->decimal('quality_score', 18, 8)->default('0');
                $table->string('status', 40)->default('RECORDED');
                $table->timestamps();
                $table->index(['market_symbol', 'created_at']);
            });
        }

        if (! Schema::hasTable('liquidity_pnl_snapshots')) {
            Schema::create('liquidity_pnl_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('snapshot_id')->unique();
                $table->string('asset', 24);
                $table->decimal('realized_pnl', 36, 18)->default('0');
                $table->decimal('unrealized_pnl', 36, 18)->default('0');
                $table->decimal('venue_fees', 36, 18)->default('0');
                $table->decimal('rebalancing_fees', 36, 18)->default('0');
                $table->decimal('sor_savings', 36, 18)->default('0');
                $table->decimal('convert_spread_revenue', 36, 18)->default('0');
                $table->json('metadata')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidity_pnl_snapshots');
        Schema::dropIfExists('best_execution_audits');
        Schema::dropIfExists('liquidity_reconciliation_differences');
        Schema::dropIfExists('liquidity_reconciliation_runs');
        Schema::dropIfExists('market_maker_quotes');
        Schema::dropIfExists('market_maker_accounts');
        Schema::dropIfExists('net_exposure_snapshots');
        Schema::dropIfExists('treasury_rebalancing_runs');
        Schema::dropIfExists('withdrawal_liquidity_reserves');
        Schema::dropIfExists('treasury_liquidity_buckets');
        Schema::dropIfExists('liquidity_reservations');
        Schema::dropIfExists('liquidity_route_executions');
        Schema::dropIfExists('liquidity_route_plans');
        Schema::dropIfExists('external_fills');
        Schema::dropIfExists('external_orders');
        Schema::dropIfExists('external_venue_balances');
        Schema::dropIfExists('external_venue_accounts');
        Schema::dropIfExists('liquidity_source_health');
        Schema::dropIfExists('liquidity_source_markets');
        Schema::dropIfExists('liquidity_sources');
    }
};
