<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('quote_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 16);
            $table->string('to_currency', 16);
            $table->decimal('amount_sent', 20, 8);
            $table->decimal('amount_received', 20, 8);
            $table->decimal('rate', 20, 8);
            $table->decimal('fee', 20, 8)->default(0);
            $table->string('route', 100);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'from_currency', 'to_currency']);
        });

        Schema::create('swaps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('swap_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('quote_id')->index();
            $table->string('from_currency', 16);
            $table->string('to_currency', 16);
            $table->decimal('amount_sent', 20, 8);
            $table->decimal('amount_received', 20, 8);
            $table->decimal('rate', 20, 8);
            $table->decimal('fee', 20, 8)->default(0);
            $table->string('status', 20)->index();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('intent_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('currency', 16);
            $table->decimal('amount', 20, 8);
            $table->string('status', 20)->index()->default('pending');
            $table->string('provider_reference')->nullable()->index();
            $table->string('bank_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('swaps');
        Schema::dropIfExists('quotes');
    }
};
