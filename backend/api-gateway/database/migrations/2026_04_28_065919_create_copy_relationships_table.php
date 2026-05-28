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
        Schema::create('copy_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trader_id')->constrained('traders')->onDelete('cascade');
            $table->decimal('amount_allocated', 20, 8);
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['active', 'paused', 'stopped'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copy_relationships');
    }
};
