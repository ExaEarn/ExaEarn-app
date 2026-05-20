<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nft_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('creator_wallet')->nullable();
            $table->unsignedSmallInteger('royalty_percentage')->default(750);
            $table->string('utility_type', 60);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nfts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('nft_uuid')->unique();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('contract_address')->nullable()->index();
            $table->foreignId('collection_id')->constrained('nft_collections')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('utility_type', 60);
            $table->string('name');
            $table->string('symbol', 24)->default('EXANFT');
            $table->string('creator_wallet')->nullable();
            $table->string('owner_wallet')->nullable();
            $table->string('tier', 40)->default('standard');
            $table->unsignedInteger('level')->default(1);
            $table->string('status', 40)->default('active');
            $table->decimal('mint_fee_exa', 20, 8)->default(0);
            $table->decimal('current_value_exa', 20, 8)->default(0);
            $table->decimal('earnings_generated_exa', 20, 8)->default(0);
            $table->string('metadata_url');
            $table->string('mint_tx_hash')->nullable();
            $table->string('last_event_tx_hash')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('benefits')->nullable();
            $table->json('upgrade_options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['utility_type', 'status']);
        });

        Schema::create('nft_listings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('listing_uuid')->unique();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seller_wallet');
            $table->decimal('price_exa', 20, 8);
            $table->string('listing_type', 32)->default('fixed_price');
            $table->string('status', 32)->default('active');
            $table->string('listing_tx_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('nft_listings')->nullOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('buyer_wallet');
            $table->string('seller_wallet');
            $table->decimal('sale_price_exa', 20, 8);
            $table->decimal('platform_fee_exa', 20, 8)->default(0);
            $table->decimal('royalty_fee_exa', 20, 8)->default(0);
            $table->string('tx_hash')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_auctions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('auction_uuid')->unique();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seller_wallet');
            $table->decimal('starting_price_exa', 20, 8);
            $table->decimal('current_highest_bid_exa', 20, 8)->default(0);
            $table->foreignId('highest_bidder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('highest_bidder_wallet')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('auction_tx_hash')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_tier', 40)->nullable();
            $table->string('to_tier', 40);
            $table->unsignedInteger('from_level')->default(1);
            $table->unsignedInteger('to_level')->default(1);
            $table->decimal('upgrade_fee_exa', 20, 8)->default(0);
            $table->decimal('burn_amount_exa', 20, 8)->default(0);
            $table->string('tx_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan', 40);
            $table->string('status', 32)->default('active');
            $table->decimal('fee_exa', 20, 8)->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('tx_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_staking_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('staked_amount_exa', 20, 8)->default(0);
            $table->decimal('reward_rate_bps', 12, 2)->default(0);
            $table->decimal('platform_commission_bps', 12, 2)->default(0);
            $table->decimal('accumulated_rewards_exa', 20, 8)->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_claimed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_fiat_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('daily_limit_usd', 20, 2)->default(0);
            $table->decimal('withdrawal_fee_bps', 12, 2)->default(0);
            $table->decimal('spread_bps', 12, 2)->default(0);
            $table->string('speed_tier', 40)->default('standard');
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_credit_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->constrained('nfts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('credit_limit_exa', 20, 8)->default(0);
            $table->decimal('available_credit_exa', 20, 8)->default(0);
            $table->decimal('interest_bps', 12, 2)->default(0);
            $table->decimal('liquidation_penalty_bps', 12, 2)->default(0);
            $table->unsignedInteger('credit_score')->default(0);
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_revenue_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 60);
            $table->decimal('gross_amount_exa', 20, 8)->default(0);
            $table->decimal('platform_revenue_exa', 20, 8)->default(0);
            $table->decimal('token_burn_exa', 20, 8)->default(0);
            $table->string('tx_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nft_revenue_events');
        Schema::dropIfExists('nft_credit_lines');
        Schema::dropIfExists('nft_fiat_profiles');
        Schema::dropIfExists('nft_staking_positions');
        Schema::dropIfExists('nft_subscriptions');
        Schema::dropIfExists('nft_upgrades');
        Schema::dropIfExists('nft_auctions');
        Schema::dropIfExists('nft_sales');
        Schema::dropIfExists('nft_listings');
        Schema::dropIfExists('nfts');
        Schema::dropIfExists('nft_collections');
    }
};

