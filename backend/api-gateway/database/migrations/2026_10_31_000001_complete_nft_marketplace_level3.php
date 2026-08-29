<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nft_collections', function (Blueprint $table): void {
            foreach (['chain', 'contract_address', 'verification_status', 'status'] as $column) {
                if (! Schema::hasColumn('nft_collections', $column)) $table->string($column)->nullable();
            }
        });
        Schema::table('nfts', function (Blueprint $table): void {
            foreach (['chain', 'token_standard', 'mint_status', 'moderation_status', 'media_url'] as $column) {
                if (! Schema::hasColumn('nfts', $column)) $table->string($column)->nullable();
            }
            if (! Schema::hasColumn('nfts', 'metadata_hash')) $table->string('metadata_hash')->nullable();
            if (! Schema::hasColumn('nfts', 'minted_at')) $table->timestamp('minted_at')->nullable();
        });
        Schema::table('nft_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('nft_listings', 'settlement_asset')) $table->string('settlement_asset', 20)->default('EXA');
            if (! Schema::hasColumn('nft_listings', 'pricing_snapshot')) $table->json('pricing_snapshot')->nullable();
            if (! Schema::hasColumn('nft_listings', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable();
                $table->unique(['seller_user_id', 'idempotency_key']);
            }
        });
        Schema::table('nft_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('nft_sales', 'settlement_asset')) $table->string('settlement_asset', 20)->default('EXA');
            if (! Schema::hasColumn('nft_sales', 'status')) $table->string('status', 40)->default('COMPLETED');
            if (! Schema::hasColumn('nft_sales', 'reservation_id')) $table->string('reservation_id')->nullable()->index();
            if (! Schema::hasColumn('nft_sales', 'network_cost_exa')) $table->decimal('network_cost_exa', 20, 8)->default('0');
            if (! Schema::hasColumn('nft_sales', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable();
                $table->unique(['buyer_user_id', 'idempotency_key']);
            }
        });

        Schema::table('nft_auctions', function (Blueprint $table): void {
            if (! Schema::hasColumn('nft_auctions', 'bid_reservation_id')) $table->string('bid_reservation_id')->nullable()->index();
        });

        Schema::create('nft_chain_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->string('operation', 60);
            $table->string('chain', 40);
            $table->string('tx_hash')->nullable()->index();
            $table->string('status', 40)->default('PENDING');
            $table->unsignedInteger('confirmations')->default(0);
            $table->json('payload')->nullable();
            $table->json('receipt')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_reconciliation_breaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->string('break_type', 80);
            $table->string('severity', 40)->default('medium');
            $table->string('status', 40)->default('OPEN');
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('nft_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('nft_listings')->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 80);
            $table->string('status', 40)->default('OPEN');
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index(['report_type', 'status']);
        });

        Schema::create('nft_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nft_id')->nullable()->constrained('nfts')->nullOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('nft_collections')->nullOnDelete();
            $table->string('media_type', 40);
            $table->string('visibility', 20)->default('PUBLIC');
            $table->string('storage_provider', 60);
            $table->string('storage_key', 700);
            $table->string('original_filename', 255)->nullable();
            $table->string('safe_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 128)->index();
            $table->string('content_hash', 128)->index();
            $table->string('status', 40)->default('READY');
            $table->string('processing_status', 40)->default('READY');
            $table->string('public_uri', 1000)->nullable();
            $table->string('metadata_uri', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->string('metadata_hash', 128)->nullable();
            $table->unsignedInteger('metadata_version')->default(1);
            $table->timestamp('immutable_finalized_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['nft_id', 'status']);
            $table->index(['media_type', 'visibility', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nft_media_assets');
        Schema::dropIfExists('nft_reconciliation_breaks');
        Schema::dropIfExists('nft_reports');
        Schema::dropIfExists('nft_chain_transactions');
    }
};
