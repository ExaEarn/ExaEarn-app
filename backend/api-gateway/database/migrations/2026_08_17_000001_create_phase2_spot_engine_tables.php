<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            if (!Schema::hasColumn('markets', 'tick_size')) {
                $table->decimal('tick_size', 36, 18)->default('0.00000001')->after('price_precision');
            }
            if (!Schema::hasColumn('markets', 'quantity_step')) {
                $table->decimal('quantity_step', 36, 18)->default('0.00000001')->after('tick_size');
            }
            if (!Schema::hasColumn('markets', 'min_notional')) {
                $table->decimal('min_notional', 36, 18)->default('0')->after('max_order_size');
            }
            if (!Schema::hasColumn('markets', 'max_notional')) {
                $table->decimal('max_notional', 36, 18)->default('0')->after('min_notional');
            }
            if (!Schema::hasColumn('markets', 'trading_status')) {
                $table->string('trading_status', 32)->default('trading')->after('status')->index();
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('orders', 'client_order_id')) {
                $table->string('client_order_id', 80)->nullable()->after('order_uuid');
            }
            if (!Schema::hasColumn('orders', 'time_in_force')) {
                $table->string('time_in_force', 16)->default('GTC')->after('type')->index();
            }
            if (!Schema::hasColumn('orders', 'post_only')) {
                $table->boolean('post_only')->default(false)->after('time_in_force')->index();
            }
            if (!Schema::hasColumn('orders', 'sequence')) {
                $table->unsignedBigInteger('sequence')->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('orders', 'reservation_id')) {
                $table->uuid('reservation_id')->nullable()->after('locked_currency')->index();
            }
            if (!Schema::hasColumn('orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('sequence');
            }
            if (!Schema::hasColumn('orders', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('accepted_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('opened_at');
            }

            $table->unique(['user_id', 'market_id', 'client_order_id'], 'orders_user_market_client_order_unique');
        });

        Schema::table('trades', function (Blueprint $table): void {
            if (!Schema::hasColumn('trades', 'sequence')) {
                $table->unsignedBigInteger('sequence')->nullable()->after('pair')->index();
            }
            if (!Schema::hasColumn('trades', 'maker_order_id')) {
                $table->foreignId('maker_order_id')->nullable()->after('sell_order_id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('trades', 'taker_order_id')) {
                $table->foreignId('taker_order_id')->nullable()->after('maker_order_id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('trades', 'settlement_status')) {
                $table->string('settlement_status', 32)->default('pending')->after('taker_fee')->index();
            }
            if (!Schema::hasColumn('trades', 'settlement_reference')) {
                $table->string('settlement_reference', 160)->nullable()->after('settlement_status')->unique();
            }
        });

        if (!Schema::hasTable('spot_engine_sequences')) {
            Schema::create('spot_engine_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('market_id')->unique()->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('spot_execution_events')) {
            Schema::create('spot_execution_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->unsignedBigInteger('sequence');
                $table->string('event_type', 48)->index();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->uuid('execution_id')->nullable()->index();
                $table->json('payload');
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();

                $table->unique(['market_id', 'sequence', 'event_type', 'order_id'], 'spot_execution_events_dedupe');
                $table->index(['market_id', 'sequence']);
            });
        }

        if (!Schema::hasTable('spot_order_book_snapshots')) {
            Schema::create('spot_order_book_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('snapshot_id')->unique();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->unsignedBigInteger('last_sequence');
                $table->json('bids');
                $table->json('asks');
                $table->json('open_orders');
                $table->string('checksum', 128);
                $table->timestamps();

                $table->index(['market_id', 'last_sequence']);
            });
        }

        if (!Schema::hasTable('spot_settlement_outbox')) {
            Schema::create('spot_settlement_outbox', function (Blueprint $table): void {
                $table->id();
                $table->uuid('outbox_id')->unique();
                $table->uuid('execution_id')->index();
                $table->foreignId('trade_id')->nullable()->constrained('trades')->nullOnDelete();
                $table->string('reference', 160)->unique();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->json('payload');
                $table->text('last_error')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_settlement_outbox');
        Schema::dropIfExists('spot_order_book_snapshots');
        Schema::dropIfExists('spot_execution_events');
        Schema::dropIfExists('spot_engine_sequences');

        Schema::table('trades', function (Blueprint $table): void {
            foreach (['settlement_reference', 'settlement_status', 'taker_order_id', 'maker_order_id', 'sequence'] as $column) {
                if (Schema::hasColumn('trades', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'client_order_id')) {
                $table->dropUnique('orders_user_market_client_order_unique');
            }
            foreach (['cancelled_at', 'opened_at', 'accepted_at', 'reservation_id', 'sequence', 'post_only', 'time_in_force', 'client_order_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('markets', function (Blueprint $table): void {
            foreach (['trading_status', 'max_notional', 'min_notional', 'quantity_step', 'tick_size'] as $column) {
                if (Schema::hasColumn('markets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
