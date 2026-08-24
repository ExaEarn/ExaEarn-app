<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copy_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('copy_orders', 'follower_spot_order_id')) {
                $table->foreignId('follower_spot_order_id')->nullable()->after('follower_futures_order_id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('copy_orders', 'follower_execution_price')) {
                $table->decimal('follower_execution_price', 36, 18)->nullable()->after('lead_execution_price');
            }
            if (!Schema::hasColumn('copy_orders', 'executed_quantity')) {
                $table->decimal('executed_quantity', 36, 18)->default('0')->after('submitted_quantity');
            }
            if (!Schema::hasColumn('copy_orders', 'executed_notional')) {
                $table->decimal('executed_notional', 36, 18)->default('0')->after('executed_quantity');
            }
            if (!Schema::hasColumn('copy_orders', 'priority')) {
                $table->unsignedSmallInteger('priority')->default(100)->after('status')->index();
            }
            if (!Schema::hasColumn('copy_orders', 'worker_token')) {
                $table->uuid('worker_token')->nullable()->after('priority')->index();
            }
        });

        Schema::table('copy_strategy_positions', function (Blueprint $table): void {
            if (!Schema::hasColumn('copy_strategy_positions', 'asset')) {
                $table->string('asset', 16)->nullable()->after('symbol')->index();
            }
            if (!Schema::hasColumn('copy_strategy_positions', 'remaining_quantity')) {
                $table->decimal('remaining_quantity', 36, 18)->default('0')->after('attributed_quantity');
            }
            if (!Schema::hasColumn('copy_strategy_positions', 'attributed_cost_basis')) {
                $table->decimal('attributed_cost_basis', 36, 18)->default('0')->after('average_entry_price');
            }
            if (!Schema::hasColumn('copy_strategy_positions', 'fees')) {
                $table->decimal('fees', 36, 18)->default('0')->after('realized_pnl');
            }
        });

        if (!Schema::hasTable('copy_realtime_events')) {
            Schema::create('copy_realtime_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('stream', 80)->default('copy')->index();
                $table->unsignedBigInteger('sequence');
                $table->string('event_type', 80)->index();
                $table->json('payload');
                $table->timestamp('published_at')->useCurrent();
                $table->timestamps();

                $table->unique(['user_id', 'stream', 'sequence'], 'copy_realtime_user_stream_sequence_unique');
                $table->index(['user_id', 'stream', 'sequence'], 'copy_realtime_replay_idx');
            });
        }

        if (!Schema::hasTable('copy_surveillance_cases')) {
            Schema::create('copy_surveillance_cases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('case_id')->unique();
                $table->foreignId('lead_trader_id')->nullable()->constrained('traders')->nullOnDelete();
                $table->foreignId('copy_relationship_id')->nullable()->constrained('copy_relationships')->nullOnDelete();
                $table->string('signal_type', 80)->index();
                $table->string('severity', 24)->default('low')->index();
                $table->json('evidence')->nullable();
                $table->json('related_accounts')->nullable();
                $table->json('markets')->nullable();
                $table->json('orders')->nullable();
                $table->json('trades')->nullable();
                $table->json('copy_orders')->nullable();
                $table->string('status', 32)->default('OPEN')->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('resolution', 120)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_load_runs')) {
            Schema::create('copy_load_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('scenario', 80)->index();
                $table->unsignedInteger('followers');
                $table->unsignedInteger('successful_decisions')->default(0);
                $table->unsignedInteger('skipped_decisions')->default(0);
                $table->unsignedInteger('failed_decisions')->default(0);
                $table->unsignedInteger('duplicate_decisions')->default(0);
                $table->unsignedInteger('orders_submitted')->default(0);
                $table->unsignedInteger('financial_invariant_failures')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->unsignedInteger('p50_decision_ms')->default(0);
                $table->unsignedInteger('p95_decision_ms')->default(0);
                $table->unsignedInteger('p99_decision_ms')->default(0);
                $table->string('status', 32)->default('PASS')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_load_runs');
        Schema::dropIfExists('copy_surveillance_cases');
        Schema::dropIfExists('copy_realtime_events');
    }
};
