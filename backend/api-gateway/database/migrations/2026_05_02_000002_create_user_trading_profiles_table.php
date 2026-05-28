<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_trading_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('skill_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->enum('risk_tolerance', ['low', 'medium', 'high'])->default('medium');
            $table->integer('preferred_leverage')->default(1)->min(1)->max(100);
            $table->decimal('max_position_size', 20, 8)->nullable();
            $table->decimal('daily_loss_limit', 20, 8)->nullable();
            $table->decimal('account_balance', 20, 8)->default(0);
            $table->integer('total_trading_experience_months')->default(0);
            $table->json('preferred_symbols')->nullable();
            $table->json('preferred_strategies')->nullable();
            $table->boolean('enable_ai_suggestions')->default(true);
            $table->boolean('enable_auto_trading')->default(false);
            $table->decimal('auto_trading_max_drawdown', 5, 2)->default(20)->min(0)->max(100);
            $table->json('ai_assistant_settings')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('skill_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_trading_profiles');
    }
};
