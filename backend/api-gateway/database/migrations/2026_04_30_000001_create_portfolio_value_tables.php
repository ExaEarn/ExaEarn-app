<?php

declare(strict_types=1);

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
        Schema::create('user_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('asset_type', ['crypto', 'fiat', 'giftcard', 'nft']);
            $table->string('asset_symbol', 120);
            $table->decimal('balance', 36, 18)->default(0);
            $table->decimal('locked_balance', 36, 18)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'asset_type', 'asset_symbol'], 'user_asset_unique');
            $table->index(['user_id', 'asset_type']);
        });

        Schema::create('price_feeds', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_symbol', 32)->unique();
            $table->decimal('price_in_usdt', 36, 18)->default(0);
            $table->string('source', 64)->default('internal');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('currency', 16)->unique();
            $table->decimal('rate_to_usdt', 36, 18)->default(0);
            $table->string('source', 64)->default('internal');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });

        Schema::create('giftcard_portfolio_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('card_type', 120)->unique();
            $table->decimal('rate_to_usdt', 36, 18)->default(0);
            $table->string('currency', 16)->default('USDT');
            $table->string('source', 64)->default('internal');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->uuid('nft_uuid')->nullable()->unique();
            $table->string('collection_name', 120)->nullable();
            $table->decimal('floor_price_usdt', 36, 18)->default(0);
            $table->decimal('last_sale_price_usdt', 36, 18)->default(0);
            $table->string('source', 64)->default('internal');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });

        Schema::create('portfolio_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('base_currency', 16)->default('USDT');
            $table->decimal('total_value', 36, 18)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'base_currency'], 'portfolio_snapshot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
        Schema::dropIfExists('nft_prices');
        Schema::dropIfExists('giftcard_portfolio_rates');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('price_feeds');
        Schema::dropIfExists('user_assets');
    }
};