<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('customer_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60)->index();
            $table->string('provider_customer_id', 160)->nullable()->index();
            $table->string('provider_status', 40)->default('PENDING')->index();
            $table->string('kyc_status', 40)->default('PENDING')->index();
            $table->string('country', 3)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider']);
        });

        Schema::create('cards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('card_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_customer_id')->constrained('card_customers')->cascadeOnDelete();
            $table->string('provider', 60)->index();
            $table->string('provider_card_id', 160)->nullable()->index();
            $table->string('card_product', 80)->index();
            $table->string('type', 40)->index();
            $table->string('currency', 24)->index();
            $table->string('network', 40)->nullable();
            $table->string('last_four', 4)->nullable();
            $table->string('expiry_month', 2)->nullable();
            $table->string('expiry_year', 4)->nullable();
            $table->string('status', 40)->default('PENDING')->index();
            $table->string('nickname', 120)->nullable();
            $table->string('physical_status', 40)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->string('idempotency_key', 180)->nullable()->unique();
            $table->json('controls')->nullable();
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('card_funding_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('quote_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('source_asset', 24);
            $table->string('card_currency', 24);
            $table->decimal('source_amount', 36, 18);
            $table->decimal('card_amount', 36, 18);
            $table->decimal('fx_rate', 36, 18)->default('1');
            $table->decimal('conversion_fee', 36, 18)->default('0');
            $table->decimal('card_fee', 36, 18)->default('0');
            $table->decimal('provider_fee', 36, 18)->default('0');
            $table->decimal('provider_cost', 36, 18)->default('0');
            $table->decimal('total_debit', 36, 18);
            $table->json('pricing_snapshot')->nullable();
            $table->string('status', 40)->default('QUOTED')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('card_funding_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('funding_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('card_funding_quote_id')->nullable()->constrained('card_funding_quotes')->nullOnDelete();
            $table->string('source_asset', 24);
            $table->string('card_currency', 24);
            $table->decimal('source_amount', 36, 18);
            $table->decimal('card_amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('provider_fee', 36, 18)->default('0');
            $table->decimal('provider_cost', 36, 18)->default('0');
            $table->decimal('total_debit', 36, 18);
            $table->string('status', 40)->default('RESERVED')->index();
            $table->string('reservation_id', 120)->nullable()->index();
            $table->string('provider_reference', 180)->nullable()->index();
            $table->string('ledger_reference', 180)->nullable()->unique();
            $table->string('idempotency_key', 180)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('card_unload_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('unload_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('asset', 24);
            $table->decimal('amount', 36, 18);
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('net_amount', 36, 18);
            $table->string('status', 40)->default('PROCESSING')->index();
            $table->string('provider_reference', 180)->nullable()->index();
            $table->string('ledger_reference', 180)->nullable()->unique();
            $table->string('idempotency_key', 180)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60)->index();
            $table->string('provider_transaction_id', 180)->nullable()->index();
            $table->string('provider_reference', 180)->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('merchant', 180)->nullable();
            $table->string('mcc', 12)->nullable();
            $table->string('country', 3)->nullable();
            $table->string('transaction_currency', 24);
            $table->decimal('transaction_amount', 36, 18);
            $table->string('billing_currency', 24);
            $table->decimal('billing_amount', 36, 18);
            $table->decimal('fee', 36, 18)->default('0');
            $table->decimal('provider_cost', 36, 18)->default('0');
            $table->decimal('fx_rate', 36, 18)->default('1');
            $table->string('authorization_reference', 180)->nullable()->index();
            $table->string('status', 40)->index();
            $table->timestamp('provider_created_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id'], 'card_tx_provider_unique');
        });

        Schema::create('card_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('authorization_uuid')->unique();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60);
            $table->string('provider_authorization_id', 180)->index();
            $table->decimal('amount', 36, 18);
            $table->string('currency', 24);
            $table->string('merchant', 180)->nullable();
            $table->string('status', 40)->default('AUTHORIZED')->index();
            $table->string('ledger_reference', 180)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_authorization_id'], 'card_auth_provider_unique');
        });

        Schema::create('card_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('provider', 60)->index();
            $table->string('provider_event_id', 180);
            $table->string('event_type', 80)->index();
            $table->string('status', 40)->default('RECEIVED')->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'card_webhook_provider_event_unique');
        });

        Schema::create('card_disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dispute_uuid')->unique();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_transaction_id')->nullable()->constrained('card_transactions')->nullOnDelete();
            $table->string('provider_dispute_id', 180)->nullable()->index();
            $table->string('status', 40)->default('OPEN')->index();
            $table->decimal('amount', 36, 18)->default('0');
            $table->string('currency', 24)->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('card_provider_balances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('balance_uuid')->unique();
            $table->string('provider', 60)->index();
            $table->string('currency', 24)->index();
            $table->decimal('available', 36, 18)->default('0');
            $table->decimal('required_minimum', 36, 18)->default('0');
            $table->decimal('target', 36, 18)->default('0');
            $table->decimal('maximum', 36, 18)->default('0');
            $table->decimal('pending_settlements', 36, 18)->default('0');
            $table->decimal('pending_refunds', 36, 18)->default('0');
            $table->string('status', 40)->default('HEALTHY')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'currency']);
        });

        Schema::create('card_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('status', 40)->default('PASS')->index();
            $table->json('summary')->nullable();
            $table->json('findings')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('card_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('audit_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120)->index();
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('card_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('order_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('provider_order_id', 180)->nullable();
            $table->json('shipping_address')->nullable();
            $table->decimal('shipping_fee', 36, 18)->default('0');
            $table->string('production_status', 40)->default('NOT_ENABLED')->index();
            $table->string('tracking_reference', 180)->nullable();
            $table->string('carrier', 80)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_orders');
        Schema::dropIfExists('card_audit_logs');
        Schema::dropIfExists('card_reconciliation_runs');
        Schema::dropIfExists('card_provider_balances');
        Schema::dropIfExists('card_disputes');
        Schema::dropIfExists('card_webhook_events');
        Schema::dropIfExists('card_authorizations');
        Schema::dropIfExists('card_transactions');
        Schema::dropIfExists('card_unload_requests');
        Schema::dropIfExists('card_funding_requests');
        Schema::dropIfExists('card_funding_quotes');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('card_customers');
    }
};
