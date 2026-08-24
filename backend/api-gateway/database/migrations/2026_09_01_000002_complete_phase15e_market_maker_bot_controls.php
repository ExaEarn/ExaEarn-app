<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_maker_bot_hedges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('hedge_uuid')->unique();
            $table->foreignId('bot_id')->constrained('market_maker_bots')->cascadeOnDelete();
            $table->foreignId('strategy_version_id')->nullable()->constrained('market_maker_bot_strategy_versions')->nullOnDelete();
            $table->string('spot_market', 48);
            $table->string('futures_market', 48);
            $table->string('mode', 40);
            $table->string('side', 20)->nullable();
            $table->decimal('target_hedge_ratio', 18, 8)->default('0');
            $table->decimal('target_notional', 36, 18)->default('0');
            $table->decimal('actual_notional', 36, 18)->default('0');
            $table->foreignId('futures_order_id')->nullable()->constrained('futures_orders')->nullOnDelete();
            $table->string('status', 40)->default('RECOMMENDED');
            $table->string('idempotency_key', 160)->unique();
            $table->json('risk_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['bot_id', 'status']);
        });

        Schema::create('market_maker_bot_rebalances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rebalance_uuid')->unique();
            $table->foreignId('bot_id')->constrained('market_maker_bots')->cascadeOnDelete();
            $table->foreignId('source_subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->foreignId('destination_subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('asset', 16);
            $table->decimal('amount', 36, 18);
            $table->string('mode', 40);
            $table->string('status', 40)->default('RECOMMENDED');
            $table->foreignId('institutional_transfer_id')->nullable()->constrained('institutional_transfer_requests')->nullOnDelete();
            $table->string('idempotency_key', 160)->unique();
            $table->json('risk_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['bot_id', 'status']);
        });

        Schema::create('market_maker_bot_load_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('scenario', 80);
            $table->unsignedInteger('bot_count');
            $table->unsignedInteger('cycles_per_bot');
            $table->string('status', 40);
            $table->json('metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_maker_bot_load_runs');
        Schema::dropIfExists('market_maker_bot_rebalances');
        Schema::dropIfExists('market_maker_bot_hedges');
    }
};
