<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trading_risk_limits')) {
            Schema::create('trading_risk_limits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('limit_id')->unique();
                $table->string('scope', 32);
                $table->string('scope_key', 96)->default('*');
                $table->string('product', 32)->default('*');
                $table->string('asset', 24)->nullable();
                $table->string('market_symbol', 48)->nullable();
                $table->decimal('max_order_notional', 36, 18)->nullable();
                $table->decimal('max_position_notional', 36, 18)->nullable();
                $table->decimal('max_borrow_amount', 36, 18)->nullable();
                $table->unsignedInteger('max_leverage')->nullable();
                $table->decimal('max_concentration_bps', 18, 8)->nullable();
                $table->string('status', 32)->default('ACTIVE');
                $table->unsignedInteger('version')->default(1);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['scope', 'scope_key', 'product']);
                $table->index(['market_symbol', 'status']);
            });
        }

        if (! Schema::hasTable('trading_user_risk_profiles')) {
            Schema::create('trading_user_risk_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('risk_tier', 32)->default('DEFAULT');
                $table->boolean('trading_enabled')->default(true);
                $table->boolean('margin_enabled')->default(false);
                $table->boolean('futures_enabled')->default(false);
                $table->string('status', 32)->default('ACTIVE');
                $table->json('restrictions')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['risk_tier', 'status']);
            });
        }

        if (! Schema::hasTable('trading_market_risk_profiles')) {
            Schema::create('trading_market_risk_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('market_symbol', 48);
                $table->string('product', 32)->default('spot');
                $table->string('risk_tier', 32)->default('DEFAULT');
                $table->decimal('max_order_notional', 36, 18)->nullable();
                $table->decimal('max_position_notional', 36, 18)->nullable();
                $table->unsignedInteger('max_leverage')->nullable();
                $table->string('status', 32)->default('ACTIVE');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['market_symbol', 'product']);
                $table->index(['product', 'status']);
            });
        }

        if (! Schema::hasTable('trading_exposure_snapshots')) {
            Schema::create('trading_exposure_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('snapshot_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product', 32);
                $table->string('asset', 24)->nullable();
                $table->string('market_symbol', 48)->nullable();
                $table->decimal('gross_exposure', 36, 18)->default('0');
                $table->decimal('net_exposure', 36, 18)->default('0');
                $table->decimal('borrowed_amount', 36, 18)->default('0');
                $table->decimal('reserved_amount', 36, 18)->default('0');
                $table->json('metadata')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();

                $table->index(['user_id', 'product', 'market_symbol'], 'trading_exposure_user_product_market_idx');
                $table->index(['product', 'asset', 'calculated_at']);
            });
        }

        if (! Schema::hasTable('trading_circuit_breakers')) {
            Schema::create('trading_circuit_breakers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('breaker_id')->unique();
                $table->string('scope', 32);
                $table->string('scope_key', 96)->default('*');
                $table->string('state', 32)->default('NORMAL');
                $table->string('reason_code', 80)->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('incident_id')->nullable();
                $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['scope', 'scope_key']);
                $table->index(['state', 'updated_at']);
            });
        }

        if (! Schema::hasTable('trading_market_states')) {
            Schema::create('trading_market_states', function (Blueprint $table): void {
                $table->id();
                $table->string('market_symbol', 48);
                $table->string('product', 32)->default('spot');
                $table->string('state', 32)->default('NORMAL');
                $table->string('reason_code', 80)->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('changed_at');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['market_symbol', 'product']);
                $table->index(['state', 'changed_at']);
            });
        }

        if (! Schema::hasTable('trading_price_snapshots')) {
            Schema::create('trading_price_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('snapshot_id')->unique();
                $table->string('market_symbol', 48);
                $table->string('product', 32)->default('spot');
                $table->string('price_type', 32);
                $table->decimal('price', 36, 18);
                $table->string('source', 80);
                $table->timestamp('source_timestamp');
                $table->json('constituents')->nullable();
                $table->json('rejected_sources')->nullable();
                $table->string('status', 32)->default('VALID');
                $table->string('calculation_version', 32)->default('phase7-v1');
                $table->timestamps();

                $table->index(['market_symbol', 'product', 'price_type', 'created_at'], 'trading_price_lookup_idx');
            });
        }

        if (! Schema::hasTable('trading_price_source_health')) {
            Schema::create('trading_price_source_health', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 80);
                $table->string('market_symbol', 48)->nullable();
                $table->string('status', 32)->default('UNKNOWN');
                $table->decimal('last_price', 36, 18)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['source', 'market_symbol']);
                $table->index(['status', 'updated_at']);
            });
        }

        if (! Schema::hasTable('trading_incidents')) {
            Schema::create('trading_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('incident_id')->unique();
                $table->string('type', 80);
                $table->string('severity', 32);
                $table->string('status', 32)->default('OPEN');
                $table->string('scope', 32)->nullable();
                $table->string('scope_key', 96)->nullable();
                $table->text('summary');
                $table->json('metadata')->nullable();
                $table->timestamp('opened_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['type', 'severity', 'status']);
                $table->index(['scope', 'scope_key']);
            });
        }

        if (! Schema::hasTable('trading_incident_events')) {
            Schema::create('trading_incident_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trading_incident_id')->constrained('trading_incidents')->cascadeOnDelete();
                $table->string('event_type', 80);
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['event_type', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('trading_incident_actions')) {
            Schema::create('trading_incident_actions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trading_incident_id')->constrained('trading_incidents')->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('action', 80);
                $table->text('reason')->nullable();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->timestamp('performed_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('financial_reconciliation_runs')) {
            Schema::create('financial_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('status', 32);
                $table->unsignedInteger('differences_count')->default(0);
                $table->json('summary')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('financial_reconciliation_differences')) {
            Schema::create('financial_reconciliation_differences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('financial_reconciliation_run_id')->constrained('financial_reconciliation_runs')->cascadeOnDelete();
                $table->string('scope', 64);
                $table->string('severity', 32);
                $table->string('code', 96);
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['scope', 'severity', 'code'], 'financial_recon_diff_scope_idx');
            });
        }

        if (! Schema::hasTable('insurance_fund_accounts')) {
            Schema::create('insurance_fund_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('fund_id')->unique();
                $table->string('product', 32);
                $table->string('asset', 24);
                $table->decimal('balance', 36, 18)->default('0');
                $table->decimal('reserved_balance', 36, 18)->default('0');
                $table->string('status', 32)->default('ACTIVE');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['product', 'asset']);
            });
        }

        if (! Schema::hasTable('insurance_fund_transactions')) {
            Schema::create('insurance_fund_transactions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('transaction_id')->unique();
                $table->foreignId('insurance_fund_account_id')->constrained('insurance_fund_accounts')->cascadeOnDelete();
                $table->string('type', 32);
                $table->decimal('amount', 36, 18);
                $table->string('reference')->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('collateral_configurations')) {
            Schema::create('collateral_configurations', function (Blueprint $table): void {
                $table->id();
                $table->string('asset', 24)->unique();
                $table->decimal('collateral_factor', 18, 8)->default('0');
                $table->decimal('max_collateral_amount', 36, 18)->nullable();
                $table->decimal('concentration_threshold_bps', 18, 8)->nullable();
                $table->string('volatility_category', 32)->default('STANDARD');
                $table->string('status', 32)->default('ACTIVE');
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('effective_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('collateral_configuration_versions')) {
            Schema::create('collateral_configuration_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('collateral_configuration_id')->constrained('collateral_configurations')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->json('before_state')->nullable();
                $table->json('after_state');
                $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamp('changed_at');
                $table->timestamps();

                $table->unique(['collateral_configuration_id', 'version'], 'collateral_config_version_unique');
            });
        }

        if (! Schema::hasTable('operational_readiness_checks')) {
            Schema::create('operational_readiness_checks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('check_id')->unique();
                $table->string('overall_status', 32);
                $table->json('components');
                $table->json('blockers')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['overall_status', 'checked_at']);
            });
        }

        if (! Schema::hasTable('trading_load_runs')) {
            Schema::create('trading_load_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('scope', 64);
                $table->unsignedInteger('iterations');
                $table->unsignedInteger('operations');
                $table->unsignedInteger('failures')->default(0);
                $table->decimal('p50_ms', 18, 6)->default('0');
                $table->decimal('p95_ms', 18, 6)->default('0');
                $table->decimal('p99_ms', 18, 6)->default('0');
                $table->decimal('duration_ms', 18, 6)->default('0');
                $table->string('status', 32)->default('PASS');
                $table->json('metrics')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trading_operational_audit_logs')) {
            Schema::create('trading_operational_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('audit_id')->unique();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('actor_type', 32)->default('system');
                $table->string('action', 96);
                $table->string('scope', 32)->nullable();
                $table->string('scope_key', 96)->nullable();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->text('reason')->nullable();
                $table->string('correlation_id', 96)->nullable();
                $table->timestamp('performed_at');
                $table->timestamps();

                $table->index(['action', 'performed_at']);
                $table->index(['scope', 'scope_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_operational_audit_logs');
        Schema::dropIfExists('trading_load_runs');
        Schema::dropIfExists('operational_readiness_checks');
        Schema::dropIfExists('collateral_configuration_versions');
        Schema::dropIfExists('collateral_configurations');
        Schema::dropIfExists('insurance_fund_transactions');
        Schema::dropIfExists('insurance_fund_accounts');
        Schema::dropIfExists('financial_reconciliation_differences');
        Schema::dropIfExists('financial_reconciliation_runs');
        Schema::dropIfExists('trading_incident_actions');
        Schema::dropIfExists('trading_incident_events');
        Schema::dropIfExists('trading_circuit_breakers');
        Schema::dropIfExists('trading_incidents');
        Schema::dropIfExists('trading_price_source_health');
        Schema::dropIfExists('trading_price_snapshots');
        Schema::dropIfExists('trading_market_states');
        Schema::dropIfExists('trading_exposure_snapshots');
        Schema::dropIfExists('trading_market_risk_profiles');
        Schema::dropIfExists('trading_user_risk_profiles');
        Schema::dropIfExists('trading_risk_limits');
    }
};
