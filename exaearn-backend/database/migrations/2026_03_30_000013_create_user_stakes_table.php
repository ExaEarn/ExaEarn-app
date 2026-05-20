<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained('staking_pools')->cascadeOnDelete();
            $table->decimal('amount', 24, 8);
            $table->decimal('compounded_amount', 24, 8)->default(0);
            $table->timestamp('lock_start');
            $table->timestamp('lock_end')->index();
            $table->timestamp('last_reward_at')->nullable();
            $table->boolean('auto_compound')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->string('tx_hash', 120)->nullable();
            $table->string('unstake_tx_hash', 120)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stakes');
    }
};

