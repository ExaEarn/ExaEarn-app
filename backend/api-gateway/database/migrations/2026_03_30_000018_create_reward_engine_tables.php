<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_type', 64)->unique();
            $table->decimal('reward_rate', 20, 8);
            $table->decimal('daily_limit', 20, 8)->default(0);
            $table->string('status', 20)->default('active');
            $table->string('mode', 20)->default('formula');
            $table->decimal('min_activity_value', 20, 8)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 64);
            $table->decimal('activity_value', 20, 8)->default(0);
            $table->decimal('reward_amount', 20, 8);
            $table->string('reward_token', 16);
            $table->string('status', 20)->default('pending');
            $table->string('activity_key', 120);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->string('distribution_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_type', 'activity_key'], 'user_rewards_unique_activity');
            $table->index(['user_id', 'status']);
        });

        Schema::create('reward_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('user_rewards')->cascadeOnDelete();
            $table->decimal('claimed_amount', 20, 8);
            $table->string('wallet_address', 255);
            $table->string('tx_hash', 255)->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('reward_id');
            $table->index(['user_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_claims');
        Schema::dropIfExists('user_rewards');
        Schema::dropIfExists('reward_activities');
    }
};

