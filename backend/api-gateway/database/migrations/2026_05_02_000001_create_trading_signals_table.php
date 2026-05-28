<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->enum('signal_type', ['BUY', 'SELL', 'HOLD']);
            $table->integer('confidence')->min(0)->max(100);
            $table->text('reason');
            $table->json('technical_indicators');
            $table->decimal('suggested_entry', 20, 8)->nullable();
            $table->decimal('suggested_stop_loss', 20, 8)->nullable();
            $table->decimal('suggested_take_profit', 20, 8)->nullable();
            $table->string('market_condition')->nullable(); // 'bullish', 'bearish', 'sideways'
            $table->string('volatility_level')->nullable(); // 'low', 'medium', 'high'
            $table->decimal('trend_strength', 3, 2)->nullable(); // 0-1
            $table->decimal('risk_reward_ratio', 4, 2)->nullable();
            $table->text('ai_reasoning')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('symbol');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_signals');
    }
};
