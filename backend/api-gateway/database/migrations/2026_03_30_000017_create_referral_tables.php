<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('referred_user_id');
            $table->unique(['referrer_user_id', 'referred_user_id']);
            $table->index(['referrer_user_id', 'created_at']);
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('reward_amount', 20, 8);
            $table->string('reward_token', 16);
            $table->string('activity_type', 64);
            $table->unsignedTinyInteger('level');
            $table->string('status', 32)->default('pending');
            $table->string('event_key', 120);
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['referred_user_id', 'activity_type', 'level', 'event_key'], 'referral_rewards_unique_event');
            $table->index(['referrer_id', 'created_at']);
            $table->index(['status', 'activity_type']);
        });

        Schema::create('referral_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('timeframe', 16);
            $table->timestamp('period_start');
            $table->timestamp('period_end')->nullable();
            $table->unsignedInteger('total_invites')->default(0);
            $table->unsignedInteger('active_invites')->default(0);
            $table->decimal('total_rewards', 20, 8)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['user_id', 'timeframe', 'period_start'], 'referral_leaderboards_unique_period');
            $table->index(['timeframe', 'total_rewards']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_leaderboards');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referrals');
    }
};

