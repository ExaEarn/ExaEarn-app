<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 16)->default('USDT');
            $table->decimal('balance', 36, 18)->default(0);
            $table->decimal('locked_balance', 36, 18)->default(0);
            $table->string('network', 32)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'asset', 'network']);
            $table->index(['asset', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
