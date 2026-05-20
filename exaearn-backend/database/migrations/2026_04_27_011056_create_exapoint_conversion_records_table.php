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
        Schema::create('exapoint_conversion_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('exapoints_converted', 20, 8);
            $table->decimal('exatokens_issued', 20, 8);
            $table->decimal('conversion_rate', 20, 8);
            $table->enum('status', ['pending', 'approved', 'completed', 'failed'])->default('pending');
            $table->string('transaction_hash')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exapoint_conversion_records');
    }
};
