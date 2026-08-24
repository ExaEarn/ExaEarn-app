<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            if (!Schema::hasColumn('markets', 'engine_mode')) {
                $table->string('engine_mode', 32)->default('legacy')->after('trading_status')->index();
            }
            if (!Schema::hasColumn('markets', 'cutover_state')) {
                $table->string('cutover_state', 48)->default('LEGACY')->after('engine_mode')->index();
            }
            if (!Schema::hasColumn('markets', 'health_status')) {
                $table->string('health_status', 32)->default('HEALTHY')->after('cutover_state')->index();
            }
            if (!Schema::hasColumn('markets', 'engine_mode_updated_at')) {
                $table->timestamp('engine_mode_updated_at')->nullable()->after('health_status');
            }
        });

        if (!Schema::hasTable('spot_cutover_journals')) {
            Schema::create('spot_cutover_journals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('cutover_id')->unique();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->string('previous_mode', 48);
                $table->string('new_mode', 48);
                $table->string('previous_state', 48)->nullable();
                $table->string('new_state', 48)->nullable();
                $table->string('status', 32)->default('completed')->index();
                $table->text('reason')->nullable();
                $table->string('initiated_by_type', 32)->default('system');
                $table->unsignedBigInteger('initiated_by_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_id')->nullable()->index();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->string('engine_owner', 120)->nullable();
                $table->unsignedBigInteger('fencing_generation')->nullable();
                $table->unsignedBigInteger('last_legacy_sequence')->nullable();
                $table->unsignedBigInteger('new_engine_sequence')->nullable();
                $table->uuid('snapshot_id')->nullable();
                $table->json('reconciliation_result')->nullable();
                $table->unsignedInteger('open_orders_before')->default(0);
                $table->unsignedInteger('open_orders_after')->default(0);
                $table->decimal('reservations_before', 36, 18)->default('0');
                $table->decimal('reservations_after', 36, 18)->default('0');
                $table->uuid('rollback_reference')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['market_id', 'created_at']);
                $table->index(['market_id', 'new_mode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_cutover_journals');

        Schema::table('markets', function (Blueprint $table): void {
            foreach (['engine_mode_updated_at', 'health_status', 'cutover_state', 'engine_mode'] as $column) {
                if (Schema::hasColumn('markets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
