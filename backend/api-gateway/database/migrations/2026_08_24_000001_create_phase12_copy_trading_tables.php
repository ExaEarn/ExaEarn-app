<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traders', function (Blueprint $table): void {
            if (!Schema::hasColumn('traders', 'lead_trader_uuid')) {
                $table->uuid('lead_trader_uuid')->nullable()->after('id')->unique();
            }
            if (!Schema::hasColumn('traders', 'display_name')) {
                $table->string('display_name', 120)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('traders', 'bio')) {
                $table->text('bio')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('traders', 'status')) {
                $table->string('status', 32)->default('pending')->after('is_master_trader')->index();
            }
            if (!Schema::hasColumn('traders', 'supported_products')) {
                $table->json('supported_products')->nullable()->after('status');
            }
            if (!Schema::hasColumn('traders', 'risk_score')) {
                $table->decimal('risk_score', 10, 4)->default('0')->after('performance_score');
            }
            if (!Schema::hasColumn('traders', 'copy_aum')) {
                $table->decimal('copy_aum', 36, 18)->default('0')->after('followers_count');
            }
            if (!Schema::hasColumn('traders', 'profit_share_rate')) {
                $table->decimal('profit_share_rate', 12, 8)->default('0')->after('copy_aum');
            }
            if (!Schema::hasColumn('traders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('profit_share_rate');
            }
            if (!Schema::hasColumn('traders', 'metadata')) {
                $table->json('metadata')->nullable()->after('approved_at');
            }
        });

        Schema::table('copy_relationships', function (Blueprint $table): void {
            if (!Schema::hasColumn('copy_relationships', 'relationship_uuid')) {
                $table->uuid('relationship_uuid')->nullable()->after('id')->unique();
            }
            if (!Schema::hasColumn('copy_relationships', 'product_scope')) {
                $table->string('product_scope', 24)->default('futures')->after('risk_level')->index();
            }
            if (!Schema::hasColumn('copy_relationships', 'copy_mode')) {
                $table->string('copy_mode', 32)->default('proportional')->after('product_scope');
            }
            if (!Schema::hasColumn('copy_relationships', 'copy_available')) {
                $table->decimal('copy_available', 36, 18)->default('0')->after('amount_allocated');
            }
            if (!Schema::hasColumn('copy_relationships', 'copy_locked')) {
                $table->decimal('copy_locked', 36, 18)->default('0')->after('copy_available');
            }
            if (!Schema::hasColumn('copy_relationships', 'copy_pnl')) {
                $table->decimal('copy_pnl', 36, 18)->default('0')->after('copy_locked');
            }
            if (!Schema::hasColumn('copy_relationships', 'fixed_amount_per_trade')) {
                $table->decimal('fixed_amount_per_trade', 36, 18)->nullable()->after('copy_mode');
            }
            if (!Schema::hasColumn('copy_relationships', 'fixed_ratio')) {
                $table->decimal('fixed_ratio', 18, 8)->nullable()->after('fixed_amount_per_trade');
            }
            if (!Schema::hasColumn('copy_relationships', 'max_amount_per_trade')) {
                $table->decimal('max_amount_per_trade', 36, 18)->nullable()->after('fixed_ratio');
            }
            if (!Schema::hasColumn('copy_relationships', 'max_daily_loss')) {
                $table->decimal('max_daily_loss', 36, 18)->nullable()->after('max_amount_per_trade');
            }
            if (!Schema::hasColumn('copy_relationships', 'max_drawdown')) {
                $table->decimal('max_drawdown', 18, 8)->nullable()->after('max_daily_loss');
            }
            if (!Schema::hasColumn('copy_relationships', 'max_leverage')) {
                $table->unsignedSmallInteger('max_leverage')->default(1)->after('max_drawdown');
            }
            if (!Schema::hasColumn('copy_relationships', 'margin_preference')) {
                $table->string('margin_preference', 24)->default('isolated')->after('max_leverage');
            }
            if (!Schema::hasColumn('copy_relationships', 'allowed_symbols')) {
                $table->json('allowed_symbols')->nullable()->after('margin_preference');
            }
            if (!Schema::hasColumn('copy_relationships', 'high_water_mark')) {
                $table->decimal('high_water_mark', 36, 18)->default('0')->after('copy_pnl');
            }
            if (!Schema::hasColumn('copy_relationships', 'metadata')) {
                $table->json('metadata')->nullable()->after('status');
            }
        });

        if (!Schema::hasTable('copy_lead_trade_events')) {
            Schema::create('copy_lead_trade_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('lead_trader_id')->constrained('traders')->cascadeOnDelete();
                $table->foreignId('lead_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('product', 16)->default('futures')->index();
                $table->string('symbol', 32)->index();
                $table->string('side', 12);
                $table->string('position_effect', 24)->default('open');
                $table->string('lead_order_id', 120)->nullable()->index();
                $table->string('lead_trade_id', 120)->index();
                $table->decimal('execution_price', 36, 18);
                $table->decimal('executed_quantity', 36, 18);
                $table->unsignedSmallInteger('leverage')->default(1);
                $table->string('margin_mode', 24)->default('cross');
                $table->unsignedBigInteger('sequence')->default(1)->index();
                $table->timestamp('executed_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['lead_trader_id', 'product', 'lead_trade_id'], 'copy_lead_trade_unique');
            });
        }

        if (!Schema::hasTable('copy_orders')) {
            Schema::create('copy_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('copy_order_uuid')->unique();
                $table->foreignId('copy_relationship_id')->constrained('copy_relationships')->cascadeOnDelete();
                $table->foreignId('lead_trade_event_id')->constrained('copy_lead_trade_events')->cascadeOnDelete();
                $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('follower_futures_order_id')->nullable()->constrained('futures_orders')->nullOnDelete();
                $table->string('status', 32)->default('queued')->index();
                $table->string('reason_code', 80)->nullable()->index();
                $table->string('product', 16)->default('futures');
                $table->string('symbol', 32)->index();
                $table->string('side', 12);
                $table->decimal('lead_execution_price', 36, 18);
                $table->decimal('target_quantity', 36, 18)->default('0');
                $table->decimal('submitted_quantity', 36, 18)->default('0');
                $table->decimal('copy_slippage_bps', 18, 8)->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('risk_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['copy_relationship_id', 'lead_trade_event_id'], 'copy_order_idempotency');
            });
        }

        if (!Schema::hasTable('copy_strategy_positions')) {
            Schema::create('copy_strategy_positions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('strategy_position_uuid')->unique();
                $table->foreignId('copy_relationship_id')->constrained('copy_relationships')->cascadeOnDelete();
                $table->foreignId('lead_trader_id')->constrained('traders')->cascadeOnDelete();
                $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('product', 16)->default('futures')->index();
                $table->string('symbol', 32)->index();
                $table->string('side', 12);
                $table->decimal('attributed_quantity', 36, 18)->default('0');
                $table->decimal('average_entry_price', 36, 18)->default('0');
                $table->decimal('realized_pnl', 36, 18)->default('0');
                $table->string('sync_status', 32)->default('synced')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['copy_relationship_id', 'product', 'symbol', 'side'], 'copy_strategy_position_unique');
            });
        }

        if (!Schema::hasTable('copy_profit_share_accruals')) {
            Schema::create('copy_profit_share_accruals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('accrual_id')->unique();
                $table->foreignId('copy_relationship_id')->constrained('copy_relationships')->cascadeOnDelete();
                $table->foreignId('lead_trader_id')->constrained('traders')->cascadeOnDelete();
                $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('asset', 16)->default('USDT');
                $table->decimal('eligible_profit', 36, 18);
                $table->decimal('profit_share_rate', 18, 8);
                $table->decimal('accrued_amount', 36, 18);
                $table->decimal('high_water_mark_before', 36, 18);
                $table->decimal('high_water_mark_after', 36, 18);
                $table->string('status', 32)->default('accrued')->index();
                $table->string('ledger_reference', 180)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_surveillance_events')) {
            Schema::create('copy_surveillance_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('surveillance_event_id')->unique();
                $table->foreignId('lead_trader_id')->nullable()->constrained('traders')->nullOnDelete();
                $table->foreignId('copy_relationship_id')->nullable()->constrained('copy_relationships')->nullOnDelete();
                $table->string('event_type', 80)->index();
                $table->string('severity', 24)->default('low')->index();
                $table->json('signals')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_surveillance_events');
        Schema::dropIfExists('copy_profit_share_accruals');
        Schema::dropIfExists('copy_strategy_positions');
        Schema::dropIfExists('copy_orders');
        Schema::dropIfExists('copy_lead_trade_events');
    }
};
