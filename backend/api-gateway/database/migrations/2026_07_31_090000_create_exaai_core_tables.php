<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exaai_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('billing_interval', 20)->default('monthly');
            $table->string('settlement_asset', 20)->default('USDT');
            $table->decimal('price', 20, 8)->default(0);
            $table->decimal('annual_price', 20, 8)->nullable();
            $table->decimal('capital_limit', 20, 8)->default(0);
            $table->unsignedInteger('max_open_positions')->default(1);
            $table->string('analytics_level', 40)->default('basic');
            $table->string('execution_tier', 40)->default('standard');
            $table->boolean('affiliate_eligible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('feature_flags')->nullable();
            $table->json('strategy_access')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_strategy_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('risk_level', 40)->default('balanced');
            $table->text('description')->nullable();
            $table->boolean('supports_spot')->default(true);
            $table->boolean('supports_futures')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('allowed_plan_codes')->nullable();
            $table->json('default_constraints')->nullable();
            $table->timestamps();
        });

        Schema::create('exaai_strategy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategy_definition_id')->constrained('exaai_strategy_definitions')->cascadeOnDelete();
            $table->string('version', 40);
            $table->boolean('is_current')->default(false);
            $table->json('config')->nullable();
            $table->json('risk_rules')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['strategy_definition_id', 'version']);
        });

        Schema::create('exaai_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('exaai_plans')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('settlement_asset', 20)->default('USDT');
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('transaction_reference', 120)->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('renewal_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('exaai_capital_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->decimal('amount', 20, 8)->default(0);
            $table->decimal('available_amount', 20, 8)->default(0);
            $table->decimal('reserved_amount', 20, 8)->default(0);
            $table->string('status', 30)->default('active');
            $table->string('reference', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'asset', 'status']);
        });

        Schema::create('exaai_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('exaai_subscriptions')->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('exaai_capital_allocations')->cascadeOnDelete();
            $table->foreignId('strategy_definition_id')->constrained('exaai_strategy_definitions')->cascadeOnDelete();
            $table->foreignId('strategy_version_id')->constrained('exaai_strategy_versions')->cascadeOnDelete();
            $table->string('mode', 20)->default('live');
            $table->string('status', 30)->default('active');
            $table->string('risk_level', 40)->default('balanced');
            $table->string('duration_label', 40)->default('manual');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->decimal('max_daily_loss', 20, 8)->nullable();
            $table->decimal('max_drawdown_percent', 10, 4)->nullable();
            $table->unsignedInteger('max_open_positions')->nullable();
            $table->json('eligible_markets')->nullable();
            $table->json('constraints')->nullable();
            $table->json('stop_conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('exaai_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('exaai_sessions')->cascadeOnDelete();
            $table->foreignId('strategy_definition_id')->nullable()->constrained('exaai_strategy_definitions')->nullOnDelete();
            $table->string('market_type', 20)->default('spot');
            $table->string('pair', 40);
            $table->string('side', 20);
            $table->string('order_type', 20)->default('market');
            $table->decimal('quantity', 20, 8)->default(0);
            $table->decimal('entry_price', 20, 8)->nullable();
            $table->decimal('exit_price', 20, 8)->nullable();
            $table->decimal('fees', 20, 8)->default(0);
            $table->decimal('realized_pnl', 20, 8)->default(0);
            $table->decimal('unrealized_pnl', 20, 8)->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('source_order_uuid', 120)->nullable()->index();
            $table->string('source_futures_order_uuid', 120)->nullable()->index();
            $table->json('signal_context')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('exaai_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('exaai_sessions')->nullOnDelete();
            $table->string('event_type', 80)->index();
            $table->string('severity', 20)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::table('exaai_plans')->insert([
            [
                'code' => 'starter',
                'name' => 'Starter',
                'billing_interval' => 'monthly',
                'settlement_asset' => 'USDT',
                'price' => '20.00000000',
                'annual_price' => '200.00000000',
                'capital_limit' => '1000.00000000',
                'max_open_positions' => 2,
                'analytics_level' => 'basic',
                'execution_tier' => 'standard',
                'affiliate_eligible' => true,
                'is_active' => true,
                'feature_flags' => json_encode(['advanced_analytics' => false, 'multi_strategy' => false]),
                'strategy_access' => json_encode(['conservative']),
                'description' => 'Suitable for users exploring automated trading with disciplined limits.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'billing_interval' => 'monthly',
                'settlement_asset' => 'USDT',
                'price' => '100.00000000',
                'annual_price' => '1000.00000000',
                'capital_limit' => '10000.00000000',
                'max_open_positions' => 6,
                'analytics_level' => 'advanced',
                'execution_tier' => 'priority',
                'affiliate_eligible' => true,
                'is_active' => true,
                'feature_flags' => json_encode(['advanced_analytics' => true, 'multi_strategy' => false]),
                'strategy_access' => json_encode(['conservative', 'balanced']),
                'description' => 'For active users who want more strategy control and deeper analytics.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'elite',
                'name' => 'Elite',
                'billing_interval' => 'monthly',
                'settlement_asset' => 'USDT',
                'price' => '250.00000000',
                'annual_price' => '2500.00000000',
                'capital_limit' => '50000.00000000',
                'max_open_positions' => 12,
                'analytics_level' => 'institutional',
                'execution_tier' => 'highest',
                'affiliate_eligible' => true,
                'is_active' => true,
                'feature_flags' => json_encode(['advanced_analytics' => true, 'multi_strategy' => true]),
                'strategy_access' => json_encode(['conservative', 'balanced', 'aggressive']),
                'description' => 'Advanced automation tier with portfolio-level controls and multi-strategy access.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('exaai_strategy_definitions')->insert([
            [
                'code' => 'conservative',
                'name' => 'Conservative',
                'risk_level' => 'conservative',
                'description' => 'Lower exposure, tighter limits, and stricter trade filtering.',
                'supports_spot' => true,
                'supports_futures' => false,
                'is_active' => true,
                'allowed_plan_codes' => json_encode(['starter', 'pro', 'elite']),
                'default_constraints' => json_encode(['max_position_pct' => '0.10', 'max_trades_per_day' => 4, 'min_signal_confidence' => 75]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'balanced',
                'name' => 'Balanced',
                'risk_level' => 'balanced',
                'description' => 'Moderate automation with broader market participation and measured exposure.',
                'supports_spot' => true,
                'supports_futures' => true,
                'is_active' => true,
                'allowed_plan_codes' => json_encode(['pro', 'elite']),
                'default_constraints' => json_encode(['max_position_pct' => '0.20', 'max_trades_per_day' => 8, 'min_signal_confidence' => 65]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'aggressive',
                'name' => 'Aggressive',
                'risk_level' => 'aggressive',
                'description' => 'Higher permitted exposure with stronger warnings and stricter stop conditions.',
                'supports_spot' => true,
                'supports_futures' => true,
                'is_active' => true,
                'allowed_plan_codes' => json_encode(['elite']),
                'default_constraints' => json_encode(['max_position_pct' => '0.30', 'max_trades_per_day' => 12, 'min_signal_confidence' => 60]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $definitions = DB::table('exaai_strategy_definitions')->get(['id', 'code']);
        foreach ($definitions as $definition) {
            DB::table('exaai_strategy_versions')->insert([
                'strategy_definition_id' => $definition->id,
                'version' => '1.0.0',
                'is_current' => true,
                'config' => json_encode(['engine' => 'rule_based', 'status' => 'active']),
                'risk_rules' => json_encode(['stale_data_seconds' => 30, 'max_slippage_bps' => 50]),
                'notes' => 'Initial ExaAI strategy release.',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exaai_audit_logs');
        Schema::dropIfExists('exaai_orders');
        Schema::dropIfExists('exaai_sessions');
        Schema::dropIfExists('exaai_capital_allocations');
        Schema::dropIfExists('exaai_subscriptions');
        Schema::dropIfExists('exaai_strategy_versions');
        Schema::dropIfExists('exaai_strategy_definitions');
        Schema::dropIfExists('exaai_plans');
    }
};
