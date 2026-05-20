<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gift card rates per brand
        Schema::create('giftcard_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('brand', 50)->unique(); // Amazon, iTunes, Google Play, etc.
            $table->decimal('rate', 5, 4); // 0.85 = 85% payout
            $table->string('currency', 3)->default('USD'); // Original currency
            $table->integer('min_value')->default(1);
            $table->integer('max_value')->default(1000);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable(); // Additional brand settings
            $table->timestamps();

            $table->index('brand');
            $table->index('active');
        });

        // Gift card submissions from users
        Schema::create('giftcard_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('brand', 50);
            $table->decimal('card_value', 18, 2);
            $table->string('currency', 3);
            $table->string('encrypted_card_code', 500); // Encrypted
            $table->string('encrypted_card_pin', 500)->nullable(); // Encrypted
            $table->enum('status', ['pending', 'verifying', 'approved', 'rejected', 'paid_out'])->default('pending');
            $table->decimal('payout_amount', 18, 2)->nullable();
            $table->decimal('rate_applied', 5, 4)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->json('verification_data')->nullable(); // External API verification results
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['brand']);
        });

        // Fraud detection records
        Schema::create('giftcard_fraud_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('flag_type', 50); // duplicate_card, unusual_pattern, rate_limit_exceeded, etc.
            $table->text('description');
            $table->decimal('score', 3, 2)->default(0); // Fraud score 0-1.0
            $table->boolean('requires_review')->default(true);
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resolved']);
            $table->index(['flag_type']);
        });

        // Gift card inventory (approved cards to sell)
        Schema::create('giftcard_inventory', function (Blueprint $table): void {
            $table->id();
            $table->string('brand', 50);
            $table->decimal('card_value', 18, 2);
            $table->string('currency', 3);
            $table->string('encrypted_card_code', 500);
            $table->string('encrypted_card_pin', 500)->nullable();
            $table->foreignId('submission_id')->nullable()->constrained('giftcard_submissions')->onDelete('set null');
            $table->boolean('available')->default(true);
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('brand');
            $table->index('available');
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giftcard_inventory');
        Schema::dropIfExists('giftcard_fraud_flags');
        Schema::dropIfExists('giftcard_submissions');
        Schema::dropIfExists('giftcard_rates');
    }
};