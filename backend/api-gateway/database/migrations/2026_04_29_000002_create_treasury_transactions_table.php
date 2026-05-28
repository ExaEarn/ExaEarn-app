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
        if (Schema::hasTable('treasury_transactions')) {
            return;
        }

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['credit', 'debit', 'transfer_to_cold', 'transfer_from_cold']);
            $table->string('asset', 10);
            $table->decimal('amount', 36, 18);
            $table->timestamp('timestamp');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
