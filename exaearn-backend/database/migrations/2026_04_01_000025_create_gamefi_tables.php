<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lottery_games', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_uuid')->unique();
            $table->unsignedBigInteger('contract_round_id')->nullable()->unique();
            $table->string('name', 120);
            $table->decimal('entry_fee_eth', 20, 8);
            $table->unsignedInteger('max_players')->nullable();
            $table->unsignedInteger('current_players')->default(0);
            $table->decimal('jackpot_amount_eth', 20, 8)->default(0);
            $table->string('trigger_type', 20)->default('max_players');
            $table->timestamp('draw_at')->nullable();
            $table->unsignedInteger('rolling_interval_seconds')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('contract_address', 100)->nullable();
            $table->string('creation_tx_hash', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('lottery_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('lottery_games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wallet_address', 100);
            $table->string('entry_tx_hash', 100)->unique();
            $table->decimal('entry_amount_eth', 20, 8);
            $table->string('status', 20)->default('pending_verification')->index();
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'user_id']);
        });

        Schema::create('lottery_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('lottery_games')->cascadeOnDelete();
            $table->string('winner_wallet', 100);
            $table->decimal('jackpot_amount_eth', 20, 8);
            $table->string('tx_hash', 100)->nullable();
            $table->timestamp('draw_time');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('betting_pools', function (Blueprint $table) {
            $table->id();
            $table->uuid('pool_uuid')->unique();
            $table->unsignedBigInteger('contract_pool_id')->nullable()->unique();
            $table->string('event_name', 180);
            $table->jsonb('bet_options');
            $table->decimal('entry_fee_eth', 20, 8)->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->string('winning_option', 80)->nullable();
            $table->timestamp('locking_at')->nullable();
            $table->string('contract_address', 100)->nullable();
            $table->string('creation_tx_hash', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('betting_pools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wallet_address', 100);
            $table->string('bet_option', 80);
            $table->decimal('bet_amount_eth', 20, 8);
            $table->string('entry_tx_hash', 100)->unique();
            $table->string('status', 20)->default('pending_verification')->index();
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['pool_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
        Schema::dropIfExists('betting_pools');
        Schema::dropIfExists('lottery_results');
        Schema::dropIfExists('lottery_entries');
        Schema::dropIfExists('lottery_games');
    }
};

