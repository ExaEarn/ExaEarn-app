<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_game_responsible_gaming_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason_category', 80)->nullable();
            $table->string('policy_version', 40)->default('exa-flight-rg-v1');
            $table->jsonb('limits')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('flight_game_risk_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->string('type', 80)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->default('OPEN')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('round_id')->nullable()->constrained('flight_game_rounds')->nullOnDelete();
            $table->foreignId('bet_id')->nullable()->constrained('flight_game_bets')->nullOnDelete();
            $table->string('asset', 16)->nullable()->index();
            $table->jsonb('evidence')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_game_risk_incidents');
        Schema::dropIfExists('flight_game_responsible_gaming_profiles');
    }
};
