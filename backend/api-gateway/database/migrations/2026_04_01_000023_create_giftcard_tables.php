<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giftcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('card_type', 120);
            $table->string('provider', 120)->nullable();
            $table->decimal('amount', 20, 8);
            $table->string('currency', 16)->default('USD');
            $table->longText('encrypted_code')->nullable();
            $table->string('card_hash', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->string('risk_level', 32)->default('LOW');
            $table->boolean('verified_source')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['card_type', 'status']);
        });

        Schema::create('giftcard_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('giftcard_id')->nullable()->constrained('giftcards')->nullOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 20, 8);
            $table->string('currency', 16)->default('USD');
            $table->string('status', 32)->default('pending');
            $table->string('risk_level', 32)->default('LOW');
            $table->unsignedInteger('risk_score')->default(0);
            $table->string('payment_method', 64)->nullable();
            $table->string('reference', 64)->unique();
            $table->boolean('requires_admin_review')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
        });

        Schema::table('giftcards', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('giftcard_orders')->nullOnDelete();
        });

        Schema::create('fraud_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('giftcard_orders')->nullOnDelete();
            $table->unsignedInteger('risk_score');
            $table->string('risk_level', 32);
            $table->jsonb('reason')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('device', 255)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['risk_level', 'created_at']);
        });

        Schema::create('suspicious_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('risk_level', 32)->default('MEDIUM');
            $table->unsignedInteger('flag_count')->default(1);
            $table->string('status', 32)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspicious_users');
        Schema::dropIfExists('fraud_logs');
        Schema::table('giftcards', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('giftcard_orders');
        Schema::dropIfExists('giftcards');
    }
};

