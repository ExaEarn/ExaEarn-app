<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_wallets', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['hot', 'cold', 'system']);
            $table->string('chain', 32);
            $table->string('address', 128)->unique();
            $table->string('label', 128)->nullable();
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'chain']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_wallets');
    }
};
