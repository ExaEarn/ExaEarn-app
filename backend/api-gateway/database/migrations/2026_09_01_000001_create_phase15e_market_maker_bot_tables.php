<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_maker_bot_strategies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('strategy_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('strategy_type', 80);
            $table->string('status', 40)->default('DRAFT');
            $table->json('supported_markets')->nullable();
            $table->json('parameters')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['market_maker_id', 'status']);
        });

        Schema::create('market_maker_bot_strategy_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('version_uuid')->unique();
            $table->foreignId('strategy_id')->constrained('market_maker_bot_strategies')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 40)->default('DRAFT');
            $table->json('parameters')->nullable();
            $table->json('supported_markets')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['strategy_id', 'version']);
        });

        Schema::create('market_maker_bots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('bot_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('developer_api_keys')->nullOnDelete();
            $table->foreignId('strategy_id')->constrained('market_maker_bot_strategies')->cascadeOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('market_maker_bot_strategy_versions')->nullOnDelete();
            $table->string('name', 120);
            $table->string('market_symbol', 48);
            $table->string('product_type', 24)->default('SPOT');
            $table->string('ownership_type', 40)->default('INSTITUTION_MANAGED');
            $table->string('status', 40)->default('DRAFT');
            $table->string('safety_state', 40)->default('NORMAL');
            $table->json('configuration')->nullable();
            $table->json('risk_limits')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('worker_id')->nullable();
            $table->timestamp('worker_lease_expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['market_symbol', 'status']);
            $table->index(['market_maker_id', 'status']);
        });

        Schema::create('market_maker_bot_quote_cycles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('cycle_uuid')->unique();
            $table->foreignId('bot_id')->constrained('market_maker_bots')->cascadeOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('market_maker_bot_strategy_versions')->nullOnDelete();
            $table->string('mode', 40)->default('SHADOW');
            $table->string('status', 40)->default('PLANNED');
            $table->string('market_symbol', 48);
            $table->decimal('fair_value', 36, 18)->nullable();
            $table->decimal('spread_bps', 18, 8)->nullable();
            $table->json('market_snapshot')->nullable();
            $table->json('inventory_snapshot')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->json('quote_plan')->nullable();
            $table->json('submitted_orders')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['bot_id', 'status']);
            $table->index(['market_symbol', 'created_at']);
        });

        Schema::create('market_maker_bot_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('bot_order_uuid')->unique();
            $table->foreignId('bot_id')->constrained('market_maker_bots')->cascadeOnDelete();
            $table->foreignId('quote_cycle_id')->nullable()->constrained('market_maker_bot_quote_cycles')->nullOnDelete();
            $table->foreignId('spot_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('futures_order_id')->nullable()->constrained('futures_orders')->nullOnDelete();
            $table->string('client_order_id', 160)->unique();
            $table->string('side', 12);
            $table->string('order_type', 24)->default('LIMIT');
            $table->decimal('price', 36, 18);
            $table->decimal('quantity', 36, 18);
            $table->string('status', 40)->default('PLANNED');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['bot_id', 'status']);
        });

        Schema::create('market_maker_bot_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->foreignId('bot_id')->nullable()->constrained('market_maker_bots')->nullOnDelete();
            $table->string('category', 80);
            $table->string('severity', 24)->default('WARNING');
            $table->string('status', 40)->default('OPEN');
            $table->string('title', 180);
            $table->json('evidence')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['category', 'status']);
        });

        Schema::create('market_maker_bot_performance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('snapshot_uuid')->unique();
            $table->foreignId('bot_id')->constrained('market_maker_bots')->cascadeOnDelete();
            $table->string('market_symbol', 48);
            $table->decimal('maker_volume', 36, 18)->default('0');
            $table->decimal('realized_pnl', 36, 18)->default('0');
            $table->decimal('unrealized_pnl', 36, 18)->default('0');
            $table->decimal('fees', 36, 18)->default('0');
            $table->decimal('rebates', 36, 18)->default('0');
            $table->decimal('drawdown_bps', 18, 8)->default('0');
            $table->decimal('cancel_ratio', 18, 8)->default('0');
            $table->json('metadata')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['market_symbol', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_maker_bot_performance_snapshots');
        Schema::dropIfExists('market_maker_bot_incidents');
        Schema::dropIfExists('market_maker_bot_orders');
        Schema::dropIfExists('market_maker_bot_quote_cycles');
        Schema::dropIfExists('market_maker_bots');
        Schema::dropIfExists('market_maker_bot_strategy_versions');
        Schema::dropIfExists('market_maker_bot_strategies');
    }
};
