<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('futures_markets', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->unsignedTinyInteger('min_leverage')->default(1);
            $table->unsignedSmallInteger('max_leverage')->default(100);
            $table->decimal('maintenance_margin_rate', 10, 8)->default(0.005);
            $table->decimal('last_price', 24, 8)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('futures_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('order_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->string('type', 16)->index(); // market, limit
            $table->string('side', 8)->index(); // long, short
            $table->decimal('price', 24, 8)->nullable();
            $table->decimal('quantity', 24, 8);
            $table->unsignedSmallInteger('leverage');
            $table->decimal('notional_value', 24, 8);
            $table->decimal('initial_margin', 24, 8);
            $table->decimal('filled_quantity', 24, 8)->default(0);
            $table->decimal('remaining_quantity', 24, 8);
            $table->string('status', 20)->default('open')->index();
            $table->string('source', 32)->default('api')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['futures_market_id', 'side', 'status', 'price', 'created_at']);
            $table->index(['user_id', 'symbol', 'status']);
        });

        Schema::create('futures_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->string('side', 8)->index(); // long, short
            $table->decimal('entry_price', 24, 8);
            $table->decimal('mark_price', 24, 8)->default(0);
            $table->decimal('quantity', 24, 8);
            $table->unsignedSmallInteger('leverage');
            $table->decimal('margin', 24, 8);
            $table->decimal('maintenance_margin', 24, 8)->default(0);
            $table->decimal('unrealized_pnl', 24, 8)->default(0);
            $table->decimal('realized_pnl', 24, 8)->default(0);
            $table->decimal('liquidation_price', 24, 8)->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'symbol', 'status']);
        });

        Schema::create('futures_trades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->foreignId('buy_order_id')->constrained('futures_orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('futures_orders')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('price', 24, 8);
            $table->decimal('quantity', 24, 8);
            $table->decimal('notional_value', 24, 8);
            $table->json('metadata')->nullable();
            $table->timestamp('executed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['futures_market_id', 'executed_at']);
        });

        Schema::create('futures_funding_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('futures_position_id')->constrained('futures_positions')->cascadeOnDelete();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->decimal('funding_rate', 16, 10);
            $table->decimal('payment_amount', 24, 8);
            $table->string('direction', 8); // pay, receive
            $table->string('reference', 120)->unique();
            $table->timestamp('funding_time')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futures_funding_payments');
        Schema::dropIfExists('futures_trades');
        Schema::dropIfExists('futures_positions');
        Schema::dropIfExists('futures_orders');
        Schema::dropIfExists('futures_markets');
    }
};

