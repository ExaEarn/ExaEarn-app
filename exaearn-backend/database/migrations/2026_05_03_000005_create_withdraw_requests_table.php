<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 16);
            $table->decimal('amount', 36, 18);
            $table->string('address', 128);
            $table->enum('status', ['pending', 'approved', 'signed', 'sent', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('signed')->default(false);
            $table->string('tx_hash', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['approved_by']);
            $table->index(['tx_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
