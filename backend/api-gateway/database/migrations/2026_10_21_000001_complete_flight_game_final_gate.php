<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_game_rounds', function (Blueprint $table): void {
            if (! Schema::hasColumn('flight_game_rounds', 'round_state')) {
                $table->string('round_state', 32)->default('OPEN')->index()->after('status');
            }
            if (! Schema::hasColumn('flight_game_rounds', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('betting_closes_at');
            }
            if (! Schema::hasColumn('flight_game_rounds', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('crashes_at');
            }
            if (! Schema::hasColumn('flight_game_rounds', 'manual_review_at')) {
                $table->timestamp('manual_review_at')->nullable()->after('settled_at');
            }
        });

        Schema::table('flight_game_bets', function (Blueprint $table): void {
            if (! Schema::hasColumn('flight_game_bets', 'reservation_id')) {
                $table->string('reservation_id', 64)->nullable()->unique()->after('idempotency_key');
            }
        });

        DB::table('flight_game_rounds')->where('status', 'betting')->update(['round_state' => 'OPEN']);
        DB::table('flight_game_rounds')->where('status', 'running')->update(['round_state' => 'RUNNING']);
        DB::table('flight_game_rounds')->where('status', 'completed')->update(['round_state' => 'SETTLED']);
        DB::table('flight_game_rounds')->where('status', 'cancelled')->update(['round_state' => 'CANCELLED']);
        DB::table('flight_game_rounds')->where('status', 'failed')->update(['round_state' => 'FAILED']);
    }

    public function down(): void
    {
        Schema::table('flight_game_bets', function (Blueprint $table): void {
            if (Schema::hasColumn('flight_game_bets', 'reservation_id')) {
                $table->dropColumn('reservation_id');
            }
        });

        Schema::table('flight_game_rounds', function (Blueprint $table): void {
            foreach (['manual_review_at', 'ended_at', 'locked_at', 'round_state'] as $column) {
                if (Schema::hasColumn('flight_game_rounds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
