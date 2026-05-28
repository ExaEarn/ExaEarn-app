<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staking_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stake_id')->constrained('user_stakes')->cascadeOnDelete();
            $table->decimal('reward_amount', 24, 8);
            $table->string('reward_token', 16)->default('EXA');
            $table->boolean('claimed')->default(false)->index();
            $table->timestamp('claimed_at')->nullable();
            $table->string('tx_hash', 120)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staking_rewards');
    }
};

