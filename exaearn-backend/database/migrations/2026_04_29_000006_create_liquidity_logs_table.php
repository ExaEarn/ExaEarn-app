<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('liquidity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->enum('action', ['rebalance', 'alert', 'threshold_breach', 'auto_adjustment']);
            $table->json('details');
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidity_logs');
    }
};