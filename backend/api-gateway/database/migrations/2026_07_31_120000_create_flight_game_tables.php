<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_game_rounds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('round_uuid')->unique();
            $table->unsignedBigInteger('round_number')->unique();
            $table->string('status', 24)->index();
            $table->string('mode', 16)->default('real')->index();
            $table->string('asset', 16)->default('USDT')->index();
            $table->string('fairness_version', 32)->default('exa-flight-v1');
            $table->string('server_seed_hash', 128);
            $table->string('server_seed', 128)->nullable();
            $table->string('client_seed', 128);
            $table->unsignedBigInteger('nonce');
            $table->decimal('crash_multiplier', 24, 8);
            $table->decimal('growth_rate', 24, 8)->default('0.16');
            $table->timestamp('betting_opens_at');
            $table->timestamp('betting_closes_at');
            $table->timestamp('starts_at');
            $table->timestamp('crashes_at');
            $table->timestamp('settled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'asset']);
        });

        Schema::create('flight_game_bets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('bet_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('flight_game_rounds')->cascadeOnDelete();
            $table->unsignedTinyInteger('panel_slot')->default(1);
            $table->string('mode', 16)->default('real');
            $table->string('asset', 16)->default('USDT');
            $table->decimal('stake', 36, 18);
            $table->decimal('auto_cashout', 24, 8)->nullable();
            $table->string('status', 24)->default('placed')->index();
            $table->decimal('cashout_multiplier', 24, 8)->nullable();
            $table->decimal('payout', 36, 18)->default(0);
            $table->decimal('profit', 36, 18)->default(0);
            $table->string('idempotency_key', 120)->unique();
            $table->string('ledger_reference', 120)->nullable()->unique();
            $table->timestamp('placed_at');
            $table->timestamp('cashed_out_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['round_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('flight_game_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('flight_game_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('round_id')->nullable()->constrained('flight_game_rounds')->nullOnDelete();
            $table->foreignId('bet_id')->nullable()->constrained('flight_game_bets')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_game_audit_logs');
        Schema::dropIfExists('flight_game_settings');
        Schema::dropIfExists('flight_game_bets');
        Schema::dropIfExists('flight_game_rounds');
    }
};