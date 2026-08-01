<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('from_account', 64);
            $table->string('to_account', 64);
            $table->string('asset', 32);
            $table->decimal('amount', 36, 18);
            $table->string('status', 32)->default('completed');
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'from_account', 'to_account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
