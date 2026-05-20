<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reward_points')->default(0);
            $table->unsignedInteger('streak_day')->default(1);
            $table->date('checkin_date');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_hash', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
            $table->index(['checkin_date', 'ip_address']);
            $table->index(['checkin_date', 'device_hash']);
        });

        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_points')->default(0);
            $table->unsignedBigInteger('available_points')->default(0);
            $table->unsignedBigInteger('redeemed_points')->default(0);
            $table->unsignedBigInteger('lifetime_points')->default(0);
            $table->timestamps();
        });

        Schema::create('checkin_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('highest_streak')->default(0);
            $table->date('last_checkin_date')->nullable();
            $table->timestamps();
        });

        Schema::create('mystery_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reward_points')->default(0);
            $table->unsignedInteger('streak_cycle');
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'streak_cycle']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('points_used');
            $table->decimal('usdt_value', 18, 8);
            $table->string('redemption_type')->default('trading_credit');
            $table->string('status')->default('approved');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('trading_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 8);
            $table->string('source')->default('daily_checkin_redemption');
            $table->boolean('locked')->default(true);
            $table->decimal('withdrawable_profit', 18, 8)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_credits');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('mystery_boxes');
        Schema::dropIfExists('checkin_streaks');
        Schema::dropIfExists('user_points');
        Schema::dropIfExists('daily_checkins');
    }
};
