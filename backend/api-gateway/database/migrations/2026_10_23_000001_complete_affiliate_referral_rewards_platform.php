<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->decimal('commission_rate_bps', 18, 8)->default('0');
            $table->decimal('monthly_cap', 36, 18)->nullable();
            $table->decimal('minimum_payout', 36, 18)->default('0');
            $table->string('payout_frequency', 40)->default('MONTHLY');
            $table->json('eligible_products')->nullable();
            $table->json('qualification_rules')->nullable();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('affiliate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_tier_id')->nullable()->constrained('affiliate_tiers')->nullOnDelete();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->string('payout_asset', 24)->default('EXAPOINT');
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_commission_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reward_policy_decision_id')->nullable()->constrained('reward_policy_decisions')->nullOnDelete();
            $table->foreignId('referral_reward_id')->nullable()->constrained('referral_rewards')->nullOnDelete();
            $table->string('product', 60)->index();
            $table->string('event_type', 80)->index();
            $table->string('source_reference', 160);
            $table->decimal('gross_revenue', 36, 18)->default('0');
            $table->decimal('commissionable_base', 36, 18)->default('0');
            $table->decimal('commission_rate_bps', 18, 8)->default('0');
            $table->decimal('commission_amount', 36, 18)->default('0');
            $table->string('reward_asset', 24)->default('EXAPOINT');
            $table->string('status', 40)->default('PENDING')->index();
            $table->timestamp('hold_until')->nullable()->index();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['product', 'event_type', 'source_reference', 'affiliate_user_id'], 'affiliate_commission_unique_event');
            $table->index(['affiliate_user_id', 'status', 'created_at'], 'affiliate_commission_user_status');
            $table->index(['referred_user_id', 'created_at']);
        });

        Schema::create('affiliate_payout_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->string('status', 40)->default('CREATED')->index();
            $table->string('asset', 24)->default('EXAPOINT');
            $table->decimal('total_amount', 36, 18)->default('0');
            $table->unsignedInteger('item_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('payout_uuid')->unique();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('affiliate_payout_batch_id')->nullable()->constrained('affiliate_payout_batches')->nullOnDelete();
            $table->string('method', 40)->default('EXAPOINT');
            $table->string('asset', 24)->default('EXAPOINT');
            $table->decimal('amount', 36, 18);
            $table->string('status', 40)->default('PENDING')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_user_id', 'status']);
        });

        Schema::create('affiliate_clawbacks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('clawback_uuid')->unique();
            $table->foreignId('affiliate_commission_event_id')->constrained('affiliate_commission_events')->cascadeOnDelete();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reversal_reference', 160);
            $table->decimal('amount', 36, 18);
            $table->string('asset', 24)->default('EXAPOINT');
            $table->string('reason_code', 120);
            $table->string('status', 40)->default('PENDING')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['affiliate_commission_event_id', 'reversal_reference'], 'affiliate_clawback_unique_reversal');
        });

        Schema::create('affiliate_reconciliation_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->string('type', 80)->index();
            $table->string('severity', 40)->default('WARNING')->index();
            $table->string('status', 40)->default('OPEN')->index();
            $table->foreignId('affiliate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('evidence');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_reconciliation_incidents');
        Schema::dropIfExists('affiliate_clawbacks');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_payout_batches');
        Schema::dropIfExists('affiliate_commission_events');
        Schema::dropIfExists('affiliate_profiles');
        Schema::dropIfExists('affiliate_tiers');
    }
};
