<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staking_pools', function (Blueprint $table) {
            $table->id();
            $table->string('asset', 16)->index();
            $table->string('reward_token', 16)->default('EXA');
            $table->unsignedBigInteger('contract_pool_id')->nullable()->unique();
            $table->unsignedInteger('lock_period');
            $table->decimal('reward_rate', 12, 8);
            $table->decimal('reward_multiplier', 12, 8)->default(1);
            $table->decimal('pool_size', 24, 8);
            $table->decimal('total_staked', 24, 8)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staking_pools');
    }
};

