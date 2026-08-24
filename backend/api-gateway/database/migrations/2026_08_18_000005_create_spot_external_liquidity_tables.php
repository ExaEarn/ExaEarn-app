<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spot_external_venue_accounts')) {
            Schema::create('spot_external_venue_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('venue', 40);
                $table->string('asset', 16);
                $table->decimal('available_balance', 36, 18)->default('0');
                $table->decimal('locked_balance', 36, 18)->default('0');
                $table->string('status', 32)->default('active')->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['venue', 'asset']);
                $table->index(['asset', 'status']);
            });
        }

        if (!Schema::hasTable('spot_external_venue_orders')) {
            Schema::create('spot_external_venue_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('external_execution_id')->unique();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->string('venue', 40)->index();
                $table->string('client_order_id', 120)->unique();
                $table->string('external_order_id', 160)->nullable()->index();
                $table->string('side', 8);
                $table->string('type', 24)->default('IOC_LIMIT');
                $table->decimal('quantity', 36, 18);
                $table->decimal('limit_price', 36, 18);
                $table->decimal('executed_quantity', 36, 18)->default('0');
                $table->decimal('executed_quote_amount', 36, 18)->default('0');
                $table->decimal('avg_execution_price', 36, 18)->default('0');
                $table->string('status', 32)->default('pending')->index();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['market_id', 'status']);
            });
        }

        if (!Schema::hasTable('spot_execution_legs')) {
            Schema::create('spot_execution_legs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('execution_leg_id')->unique();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
                $table->string('market_symbol', 32)->index();
                $table->string('venue', 40)->index();
                $table->string('liquidity_source', 40)->index();
                $table->string('side', 8);
                $table->decimal('quantity', 36, 18);
                $table->decimal('price', 36, 18);
                $table->decimal('quote_amount', 36, 18);
                $table->decimal('fee_amount', 36, 18)->default('0');
                $table->string('fee_asset', 16)->nullable();
                $table->uuid('external_execution_id')->nullable()->index();
                $table->string('ledger_reference', 180)->nullable()->unique();
                $table->string('status', 32)->default('pending')->index();
                $table->json('metadata')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();

                $table->index(['market_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_execution_legs');
        Schema::dropIfExists('spot_external_venue_orders');
        Schema::dropIfExists('spot_external_venue_accounts');
    }
};
