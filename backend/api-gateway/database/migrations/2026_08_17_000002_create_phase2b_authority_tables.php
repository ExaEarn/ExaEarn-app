<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spot_market_engine_leases')) {
            Schema::create('spot_market_engine_leases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('market_id')->unique()->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->string('owner_instance_id', 120)->index();
                $table->uuid('lease_token')->unique();
                $table->unsignedBigInteger('generation')->default(1);
                $table->timestamp('acquired_at');
                $table->timestamp('heartbeat_at');
                $table->timestamp('expires_at')->index();
                $table->string('status', 32)->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('spot_market_data_events')) {
            Schema::create('spot_market_data_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->unsignedBigInteger('sequence');
                $table->string('event_type', 48)->index();
                $table->json('payload');
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();

                $table->index(['market_id', 'sequence']);
                $table->index(['market_id', 'sequence', 'event_type'], 'spot_market_data_events_sequence_type_index');
            });
        }

        if (!Schema::hasTable('spot_shadow_comparisons')) {
            Schema::create('spot_shadow_comparisons', function (Blueprint $table): void {
                $table->id();
                $table->uuid('comparison_id')->unique();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->string('classification', 48)->index();
                $table->json('legacy_result')->nullable();
                $table->json('new_engine_result')->nullable();
                $table->json('differences')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('spot_engine_load_runs')) {
            Schema::create('spot_engine_load_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('market_symbol', 32)->index();
                $table->unsignedInteger('orders_submitted');
                $table->unsignedInteger('orders_accepted');
                $table->unsignedInteger('trades_created');
                $table->decimal('duration_ms', 18, 3);
                $table->decimal('p50_latency_ms', 18, 3);
                $table->decimal('p95_latency_ms', 18, 3);
                $table->decimal('p99_latency_ms', 18, 3);
                $table->unsignedInteger('error_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_engine_load_runs');
        Schema::dropIfExists('spot_shadow_comparisons');
        Schema::dropIfExists('spot_market_data_events');
        Schema::dropIfExists('spot_market_engine_leases');
    }
};
