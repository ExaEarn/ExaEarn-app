<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('futures_markets', function (Blueprint $table): void {
            if (!Schema::hasColumn('futures_markets', 'base_asset')) {
                $table->string('base_asset', 16)->nullable()->after('symbol')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'quote_asset')) {
                $table->string('quote_asset', 16)->default('USDT')->after('base_asset')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'settlement_asset')) {
                $table->string('settlement_asset', 16)->default('USDT')->after('quote_asset')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'contract_type')) {
                $table->string('contract_type', 24)->default('PERPETUAL')->after('settlement_asset')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'tick_size')) {
                $table->decimal('tick_size', 24, 8)->default(0.01)->after('last_price');
            }
            if (!Schema::hasColumn('futures_markets', 'quantity_step')) {
                $table->decimal('quantity_step', 24, 8)->default(0.0001)->after('tick_size');
            }
            if (!Schema::hasColumn('futures_markets', 'min_quantity')) {
                $table->decimal('min_quantity', 24, 8)->default(0.0001)->after('quantity_step');
            }
            if (!Schema::hasColumn('futures_markets', 'max_quantity')) {
                $table->decimal('max_quantity', 24, 8)->default(100)->after('min_quantity');
            }
            if (!Schema::hasColumn('futures_markets', 'min_notional')) {
                $table->decimal('min_notional', 24, 8)->default(5)->after('max_quantity');
            }
            if (!Schema::hasColumn('futures_markets', 'max_notional')) {
                $table->decimal('max_notional', 24, 8)->default(1000000)->after('min_notional');
            }
            if (!Schema::hasColumn('futures_markets', 'index_price')) {
                $table->decimal('index_price', 24, 8)->default(0)->after('max_notional');
            }
            if (!Schema::hasColumn('futures_markets', 'mark_price')) {
                $table->decimal('mark_price', 24, 8)->default(0)->after('index_price');
            }
            if (!Schema::hasColumn('futures_markets', 'funding_rate')) {
                $table->decimal('funding_rate', 16, 10)->default(0)->after('mark_price');
            }
            if (!Schema::hasColumn('futures_markets', 'next_funding_time')) {
                $table->timestamp('next_funding_time')->nullable()->after('funding_rate')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'engine_mode')) {
                $table->string('engine_mode', 16)->default('legacy')->after('status')->index();
            }
            if (!Schema::hasColumn('futures_markets', 'risk_tiers')) {
                $table->json('risk_tiers')->nullable()->after('engine_mode');
            }
            if (!Schema::hasColumn('futures_markets', 'price_band_bps')) {
                $table->unsignedInteger('price_band_bps')->default(500)->after('risk_tiers');
            }
            if (!Schema::hasColumn('futures_markets', 'metadata')) {
                $table->json('metadata')->nullable()->after('price_band_bps');
            }
        });

        Schema::table('futures_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('futures_orders', 'client_order_id')) {
                $table->string('client_order_id', 80)->nullable()->after('order_uuid');
                $table->unique(['user_id', 'client_order_id']);
            }
            if (!Schema::hasColumn('futures_orders', 'time_in_force')) {
                $table->string('time_in_force', 12)->default('GTC')->after('type')->index();
            }
            if (!Schema::hasColumn('futures_orders', 'reduce_only')) {
                $table->boolean('reduce_only')->default(false)->after('side')->index();
            }
            if (!Schema::hasColumn('futures_orders', 'post_only')) {
                $table->boolean('post_only')->default(false)->after('reduce_only')->index();
            }
            if (!Schema::hasColumn('futures_orders', 'trigger_price')) {
                $table->decimal('trigger_price', 24, 8)->nullable()->after('price');
            }
            if (!Schema::hasColumn('futures_orders', 'trigger_source')) {
                $table->string('trigger_source', 12)->default('MARK')->after('trigger_price')->index();
            }
        });

        Schema::table('futures_positions', function (Blueprint $table): void {
            if (!Schema::hasColumn('futures_positions', 'bankruptcy_price')) {
                $table->decimal('bankruptcy_price', 24, 8)->default(0)->after('liquidation_price');
            }
            if (!Schema::hasColumn('futures_positions', 'isolated_margin')) {
                $table->decimal('isolated_margin', 24, 8)->default(0)->after('margin');
            }
            if (!Schema::hasColumn('futures_positions', 'accumulated_funding')) {
                $table->decimal('accumulated_funding', 24, 8)->default(0)->after('realized_pnl');
            }
        });

        Schema::create('futures_index_price_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('index_price', 24, 8);
            $table->json('constituents');
            $table->string('status', 20)->default('healthy')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at')->index();
            $table->timestamps();
        });

        Schema::create('futures_mark_price_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('index_price', 24, 8);
            $table->decimal('mark_price', 24, 8);
            $table->decimal('premium_rate', 16, 10)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at')->index();
            $table->timestamps();
        });

        Schema::create('futures_funding_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('index_price', 24, 8);
            $table->decimal('mark_price', 24, 8);
            $table->decimal('funding_rate', 16, 10);
            $table->timestamp('funding_time')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['symbol', 'funding_time']);
        });

        Schema::create('futures_liquidation_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('liquidation_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('futures_position_id')->constrained('futures_positions')->cascadeOnDelete();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('mark_price', 24, 8);
            $table->decimal('liquidation_price', 24, 8);
            $table->decimal('quantity', 24, 8);
            $table->decimal('liquidation_fee', 24, 8)->default(0);
            $table->decimal('insurance_impact', 24, 8)->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->string('ledger_reference', 160)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('futures_adl_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('adl_id')->unique();
            $table->string('symbol', 32)->index();
            $table->foreignId('futures_position_id')->nullable()->constrained('futures_positions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('rank_score', 24, 8)->default(0);
            $table->decimal('quantity', 24, 8)->default(0);
            $table->string('status', 20)->default('queued')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('futures_reconciliation_findings', function (Blueprint $table): void {
            $table->id();
            $table->string('finding_id', 80)->unique();
            $table->string('scope', 40)->index();
            $table->string('symbol', 32)->nullable()->index();
            $table->string('severity', 20)->default('warning')->index();
            $table->string('status', 20)->default('open')->index();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futures_reconciliation_findings');
        Schema::dropIfExists('futures_adl_events');
        Schema::dropIfExists('futures_liquidation_events');
        Schema::dropIfExists('futures_funding_rates');
        Schema::dropIfExists('futures_mark_price_snapshots');
        Schema::dropIfExists('futures_index_price_snapshots');
    }
};
