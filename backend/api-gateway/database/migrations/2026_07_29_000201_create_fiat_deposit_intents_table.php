<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiat_deposit_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method_id', 64);
            $table->string('currency', 16);
            $table->decimal('gross_amount', 24, 18);
            $table->decimal('fee_amount', 24, 18)->default('0');
            $table->decimal('net_amount', 24, 18)->default('0');
            $table->string('rate_bps', 32)->nullable();
            $table->decimal('fixed_fee', 24, 18)->default('0');
            $table->string('route_destination', 32)->default('Funding');
            $table->string('status', 32)->default('pending');
            $table->json('instructions')->nullable();
            $table->json('disclosures')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiat_deposit_intents');
    }
};
