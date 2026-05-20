<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            $table->uuid('ad_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);
            $table->string('asset', 16);
            $table->string('fiat_currency', 16);
            $table->decimal('price', 20, 8);
            $table->decimal('min_limit', 20, 8);
            $table->decimal('max_limit', 20, 8);
            $table->decimal('available_amount', 20, 8);
            $table->jsonb('payment_methods');
            $table->string('region', 32)->nullable();
            $table->unsignedInteger('payment_time_limit_minutes')->default(15);
            $table->text('terms_of_trade')->nullable();
            $table->boolean('requires_kyc')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset', 'fiat_currency', 'type', 'status']);
            $table->index(['region', 'status']);
        });

        Schema::create('p2p_trades', function (Blueprint $table) {
            $table->id();
            $table->uuid('trade_uuid')->unique();
            $table->foreignId('ad_id')->constrained('p2p_ads')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('escrow_holder_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset', 16);
            $table->string('fiat_currency', 16);
            $table->decimal('crypto_amount', 20, 8);
            $table->decimal('fiat_amount', 20, 8);
            $table->decimal('price', 20, 8);
            $table->string('payment_method', 64);
            $table->unsignedInteger('payment_window_minutes');
            $table->timestamp('payment_deadline');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('escrow_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('release_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('return_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('payment_sent_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('p2p_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('p2p_trades')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('encrypted_message')->nullable();
            $table->string('attachment')->nullable();
            $table->string('moderation_status', 20)->default('pending');
            $table->jsonb('moderation_flags')->nullable();
            $table->timestamps();

            $table->index(['trade_id', 'created_at']);
        });

        Schema::create('p2p_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('p2p_trades')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->jsonb('evidence')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('p2p_trades')->cascadeOnDelete();
            $table->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('comment', 280)->nullable();
            $table->timestamps();

            $table->unique(['trade_id', 'rater_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_ratings');
        Schema::dropIfExists('p2p_disputes');
        Schema::dropIfExists('p2p_messages');
        Schema::dropIfExists('p2p_trades');
        Schema::dropIfExists('p2p_ads');
    }
};

