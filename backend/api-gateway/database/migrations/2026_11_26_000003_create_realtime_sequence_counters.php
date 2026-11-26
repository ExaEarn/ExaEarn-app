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
        Schema::create('realtime_sequence_counters', function (Blueprint $table): void {
            $table->id();
            $table->char('stream_digest', 64)->unique();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('realtime_sequence_counters') && DB::table('realtime_sequence_counters')->where('last_sequence', '>', 0)->exists()) {
            throw new RuntimeException('Realtime sequence state exists; preserve counters before rolling back this migration.');
        }

        Schema::dropIfExists('realtime_sequence_counters');
    }
};
