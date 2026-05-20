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
        Schema::create('treasury_balances', function (Blueprint $table) {
            $table->id();
            $table->string('asset', 10)->unique();
            $table->decimal('balance', 36, 18)->default(0);
            $table->decimal('hot_wallet_balance', 36, 18)->default(0);
            $table->decimal('cold_wallet_balance', 36, 18)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_balances');
    }
};