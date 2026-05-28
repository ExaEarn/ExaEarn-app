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
            $table->string('provider', 50);
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'fee', 'adjustment']);
            $table->decimal('amount', 36, 18);
            $table->string('currency', 3);
            $table->string('reference', 255)->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['currency', 'created_at']);
            $table->index(['reference']);
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
