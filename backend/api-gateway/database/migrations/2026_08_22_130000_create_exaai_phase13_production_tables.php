<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exaai_strategy_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('exaai_strategy_versions', 'state')) {
                $table->string('state', 30)->default('active')->index();
            }
            if (! Schema::hasColumn('exaai_strategy_versions', 'supported_products')) {
                $table->json('supported_products')->nullable();
            }
            if (! Schema::hasColumn('exaai_strategy_versions', 'supported_markets')) {
                $table->json('supported_markets')->nullable();
            }
            if (! Schema::hasColumn('exaai_strategy_versions', 'activated_at')) {
                $table->timestamp('activated_at')->nullable();
            }
            if (! Schema::hasColumn('exaai_strategy_versions', 'retired_at')) {
                $table->timestamp('retired_at')->nullable();
            }
        });

        Schema::create('exaai_portfolios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('exaai_sessions')->nullOnDelete();
            $table->foreignId('allocation_id')->nullable()->constrained('exaai_capital_allocations')->nullOnDelete();
            $table->foreignId('strategy_definition_id')->nullable()->constrained('exaai_strategy_definitions')->nullOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('exaai_strategy_versions')->nullOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->string('mode', 20)->default('live');
            $table->string('status', 30)->default('active')->index();
            $table->decimal('allocated_amount', 28, 8)->default(0);
            $table->decimal('available_amount', 28, 8)->default(0);
            $table->decimal('reserved_amount', 28, 8)->default(0);
            $table->decimal('deployed_amount', 28, 8)->default(0);
            $table->decimal('equity_amount', 28, 8)->default(0);
            $table->decimal('realized_pnl', 28, 8)->default(0);
            $table->decimal('unrealized_pnl', 28, 8)->default(0);
            $table->decimal('high_water_mark', 28, 8)->default(0);
            $table->string('risk_profile', 40)->default('balanced');
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('exaai_market_eligibilities', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40);
            $table->string('product', 20)->default('spot');
            $table->string('status', 30)->default('enabled');
            $table->string('risk_tier', 40)->default('standard');
            $table->decimal('min_liquidity', 28, 8)->default(0);
            $table->decimal('max_exposure', 28, 8)->default(0);
            $table->decimal('max_concentration_percent', 10, 4)->default(25);
            $table->unsignedInteger('max_slippage_bps')->default(50);
            $table->unsignedInteger('market_data_freshness_seconds')->default(30);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['symbol', 'product']);
        });

        Schema::create('exaai_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('decision_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('exaai_sessions')->nullOnDelete();
            $table->foreignId('portfolio_id')->nullable()->constrained('exaai_portfolios')->nullOnDelete();
            $table->foreignId('strategy_definition_id')->nullable()->constrained('exaai_strategy_definitions')->nullOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('exaai_strategy_versions')->nullOnDelete();
            $table->string('idempotency_key', 160);
            $table->string('product', 20)->default('spot');
            $table->string('symbol', 40);
            $table->string('side', 20);
            $table->string('order_type', 20)->default('market');
            $table->decimal('requested_notional', 28, 8)->default(0);
            $table->decimal('approved_notional', 28, 8)->default(0);
            $table->decimal('reference_price', 28, 8)->nullable();
            $table->decimal('quantity', 28, 8)->nullable();
            $table->unsignedInteger('confidence')->default(0);
            $table->string('risk_decision', 30)->default('pending');
            $table->string('status', 30)->default('pending')->index();
            $table->string('reason_code', 80)->nullable();
            $table->json('signal_payload')->nullable();
            $table->json('market_snapshot')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->json('execution_plan')->nullable();
            $table->json('execution_result')->nullable();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['session_id', 'status']);
            $table->index(['symbol', 'product', 'created_at']);
        });

        Schema::create('exaai_position_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->nullable()->constrained('exaai_portfolios')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('exaai_sessions')->nullOnDelete();
            $table->foreignId('strategy_definition_id')->nullable()->constrained('exaai_strategy_definitions')->nullOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('exaai_strategy_versions')->nullOnDelete();
            $table->string('product', 20)->default('spot');
            $table->string('symbol', 40);
            $table->string('asset', 20);
            $table->string('side', 20)->default('long');
            $table->decimal('attributed_quantity', 28, 8)->default(0);
            $table->decimal('remaining_quantity', 28, 8)->default(0);
            $table->decimal('average_entry_price', 28, 8)->default(0);
            $table->decimal('cost_basis', 28, 8)->default(0);
            $table->decimal('realized_pnl', 28, 8)->default(0);
            $table->decimal('unrealized_pnl', 28, 8)->default(0);
            $table->decimal('fees', 28, 8)->default(0);
            $table->string('sync_status', 30)->default('in_sync');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'product', 'symbol']);
        });

        Schema::create('exaai_realtime_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('stream', 80);
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 80);
            $table->json('payload');
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'stream', 'sequence']);
            $table->index(['user_id', 'stream', 'created_at']);
        });

        Schema::create('exaai_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_uuid', 80)->unique();
            $table->string('status', 30)->default('completed');
            $table->unsignedInteger('portfolios_checked')->default(0);
            $table->unsignedInteger('decisions_checked')->default(0);
            $table->unsignedInteger('differences_found')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_reconciliation_differences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('exaai_reconciliation_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('difference_type', 80);
            $table->string('severity', 20)->default('warning');
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_surveillance_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_uuid', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signal_type', 80);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('open')->index();
            $table->json('evidence')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_load_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_uuid', 80)->unique();
            $table->string('scenario', 80);
            $table->unsignedInteger('participants')->default(0);
            $table->json('metrics')->nullable();
            $table->string('status', 30)->default('completed');
            $table->timestamps();
        });

        Schema::create('exaai_backtests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('backtest_uuid')->unique();
            $table->foreignId('strategy_definition_id')->constrained('exaai_strategy_definitions')->cascadeOnDelete();
            $table->foreignId('strategy_version_id')->constrained('exaai_strategy_versions')->cascadeOnDelete();
            $table->string('dataset_reference', 160);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('parameters')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('results')->nullable();
            $table->string('status', 30)->default('completed');
            $table->timestamps();
        });

        Schema::create('exaai_public_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('exaai_term_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version', 40);
            $table->string('acceptance_scope', 80)->default('exaai_automated_trading');
            $table->timestamp('accepted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'terms_version', 'acceptance_scope']);
        });

        Schema::create('exaai_operational_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('metric_key', 120);
            $table->decimal('metric_value', 36, 8)->default(0);
            $table->json('dimensions')->nullable();
            $table->timestamp('measured_at')->useCurrent();
            $table->timestamps();
            $table->index(['metric_key', 'measured_at']);
        });

        Schema::create('exaai_operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('alert_uuid', 80)->unique();
            $table->string('dedupe_key', 160);
            $table->string('severity', 20)->default('WARNING');
            $table->string('status', 30)->default('OPEN')->index();
            $table->string('component', 80);
            $table->string('condition', 120);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['dedupe_key', 'status']);
        });

        Schema::create('exaai_operational_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('incident_uuid', 80)->unique();
            $table->string('severity', 20)->default('SEV3');
            $table->string('status', 30)->default('OPEN')->index();
            $table->string('component', 80);
            $table->string('incident_type', 120);
            $table->foreignId('portfolio_id')->nullable()->constrained('exaai_portfolios')->nullOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('exaai_strategy_versions')->nullOnDelete();
            $table->string('market_symbol', 40)->nullable();
            $table->json('expected_state')->nullable();
            $table->json('actual_state')->nullable();
            $table->json('difference')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_strategy_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategy_version_id')->constrained('exaai_strategy_versions')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_state', 40);
            $table->string('new_state', 40);
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exaai_strategy_transitions');
        Schema::dropIfExists('exaai_operational_incidents');
        Schema::dropIfExists('exaai_operational_alerts');
        Schema::dropIfExists('exaai_operational_metrics');
        Schema::dropIfExists('exaai_term_acceptances');
        Schema::dropIfExists('exaai_public_settings');
        Schema::dropIfExists('exaai_backtests');
        Schema::dropIfExists('exaai_load_runs');
        Schema::dropIfExists('exaai_surveillance_cases');
        Schema::dropIfExists('exaai_reconciliation_differences');
        Schema::dropIfExists('exaai_reconciliation_runs');
        Schema::dropIfExists('exaai_realtime_events');
        Schema::dropIfExists('exaai_position_attributions');
        Schema::dropIfExists('exaai_decisions');
        Schema::dropIfExists('exaai_market_eligibilities');
        Schema::dropIfExists('exaai_portfolios');

        Schema::table('exaai_strategy_versions', function (Blueprint $table): void {
            foreach (['state', 'supported_products', 'supported_markets', 'activated_at', 'retired_at'] as $column) {
                if (Schema::hasColumn('exaai_strategy_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
