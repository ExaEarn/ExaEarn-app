<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('futures_positions', 'margin_type')) {
            Schema::table('futures_positions', function (Blueprint $table): void {
                $table->string('margin_type', 16)->default('cross')->after('leverage')->index();
            });
        }

        Schema::create('futures_conditional_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('conditional_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('futures_position_id')->nullable()->constrained('futures_positions')->cascadeOnDelete();
            $table->foreignId('futures_market_id')->constrained('futures_markets')->cascadeOnDelete();
            $table->string('symbol', 32)->index();
            $table->string('type', 20)->index();
            $table->string('trigger_order_type', 16)->default('market');
            $table->decimal('trigger_price', 24, 8);
            $table->decimal('execution_price', 24, 8)->nullable();
            $table->decimal('quantity', 24, 8);
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 32)->default('api');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['futures_market_id', 'symbol', 'status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futures_conditional_orders');

        Schema::table('futures_positions', function (Blueprint $table): void {
            $table->dropColumn('margin_type');
        });
    }
};
