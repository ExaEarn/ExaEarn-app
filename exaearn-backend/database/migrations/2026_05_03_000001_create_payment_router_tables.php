<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->json('countries')->nullable();
            $table->json('currencies')->nullable();
            $table->decimal('fee_percentage', 8, 6)->default(0.0);
            $table->decimal('flat_fee', 20, 8)->default(0.0);
            $table->string('status', 32)->default('active');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->decimal('reliability', 5, 4)->default(0.90);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('provider', 64);
            $table->string('currency', 16);
            $table->decimal('amount', 20, 8);
            $table->decimal('provider_fee', 20, 8)->default(0.0);
            $table->decimal('markup_fee', 20, 8)->default(0.0);
            $table->decimal('system_fee', 20, 8)->default(0.0);
            $table->decimal('net_amount', 20, 8)->default(0.0);
            $table->string('status', 32)->default('pending');
            $table->string('reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider', 'status']);
            $table->index(['reference', 'provider_reference']);
        });

        Schema::table('payment_intents', function (Blueprint $table): void {
            if (!Schema::hasColumn('payment_intents', 'provider_fee')) {
                $table->decimal('provider_fee', 20, 8)->default(0.0)->after('amount');
            }
            if (!Schema::hasColumn('payment_intents', 'markup_fee')) {
                $table->decimal('markup_fee', 20, 8)->default(0.0)->after('provider_fee');
            }
            if (!Schema::hasColumn('payment_intents', 'system_fee')) {
                $table->decimal('system_fee', 20, 8)->default(0.0)->after('markup_fee');
            }
            if (!Schema::hasColumn('payment_intents', 'net_amount')) {
                $table->decimal('net_amount', 20, 8)->default(0.0)->after('system_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            $table->dropColumn(['provider_fee', 'markup_fee', 'system_fee', 'net_amount']);
        });

        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_providers');
    }
};
