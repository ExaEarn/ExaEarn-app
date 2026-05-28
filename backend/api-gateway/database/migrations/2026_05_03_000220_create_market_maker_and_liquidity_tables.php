<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_maker_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40)->unique();
            $table->decimal('spread_percentage', 8, 4)->default(1.0);
            $table->decimal('order_size', 20, 8)->default(10);
            $table->unsignedInteger('max_orders')->default(6);
            $table->string('status', 20)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('liquidity_pools', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 40)->unique();
            $table->decimal('base_asset_balance', 30, 8)->default(0);
            $table->decimal('quote_asset_balance', 30, 8)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidity_pools');
        Schema::dropIfExists('market_maker_configs');
    }
};
