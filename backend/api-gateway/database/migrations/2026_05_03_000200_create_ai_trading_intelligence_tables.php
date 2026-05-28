<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_data', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40);
            $table->decimal('price', 20, 8);
            $table->decimal('volume', 20, 8)->default(0);
            $table->decimal('spread', 12, 6)->default(0);
            $table->decimal('volatility', 12, 6)->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();
            $table->index(['symbol', 'timestamp']);
        });

        Schema::create('ai_decision_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40);
            $table->string('decision_type', 50);
            $table->json('inputs')->nullable();
            $table->json('outputs')->nullable();
            $table->json('safety_applied')->nullable();
            $table->boolean('manual_override')->default(false);
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['symbol', 'decided_at']);
        });

        Schema::create('ai_risk_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('symbol', 40)->nullable();
            $table->string('alert_type', 80);
            $table->unsignedTinyInteger('severity')->default(1);
            $table->json('details')->nullable();
            $table->string('action_taken', 120)->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['alert_type', 'detected_at']);
        });

        Schema::create('ai_system_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40)->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('params')->nullable();
            $table->unsignedBigInteger('set_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['symbol', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_system_overrides');
        Schema::dropIfExists('ai_risk_alerts');
        Schema::dropIfExists('ai_decision_logs');
        Schema::dropIfExists('market_data');
    }
};
