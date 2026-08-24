<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rule_uuid')->unique();
            $table->string('name', 160);
            $table->string('product', 60)->index();
            $table->string('operation', 80)->index();
            $table->string('fee_type', 40);
            $table->decimal('value', 36, 18)->default('0');
            $table->decimal('fixed_value', 36, 18)->default('0');
            $table->decimal('percentage_bps', 18, 8)->default('0');
            $table->decimal('spread_bps', 18, 8)->default('0');
            $table->decimal('min_fee', 36, 18)->nullable();
            $table->decimal('max_fee', 36, 18)->nullable();
            $table->string('currency', 24)->nullable()->index();
            $table->string('asset', 24)->nullable()->index();
            $table->string('network', 64)->nullable()->index();
            $table->string('market_symbol', 40)->nullable()->index();
            $table->string('country', 3)->nullable()->index();
            $table->string('vip_tier', 24)->nullable()->index();
            $table->string('merchant_tier', 40)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->string('promotion_code', 80)->nullable()->index();
            $table->string('precedence_scope', 40)->default('PRODUCT_DEFAULT')->index();
            $table->integer('priority')->default(0)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 40)->default('DRAFT')->index();
            $table->boolean('allow_negative')->default(false);
            $table->boolean('requires_maker_checker')->default(true);
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_until')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['product', 'operation', 'status', 'effective_from'], 'pricing_rules_active_lookup');
            $table->index(['precedence_scope', 'priority', 'version'], 'pricing_rules_precedence_lookup');
        });

        Schema::create('pricing_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('decision_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->unsignedInteger('rule_version')->nullable();
            $table->string('product', 60)->index();
            $table->string('operation', 80)->index();
            $table->string('fee_type', 40);
            $table->decimal('gross_amount', 36, 18)->default('0');
            $table->decimal('fee_amount', 36, 18)->default('0');
            $table->decimal('discount_amount', 36, 18)->default('0');
            $table->decimal('rebate_amount', 36, 18)->default('0');
            $table->decimal('network_fee_amount', 36, 18)->default('0');
            $table->decimal('provider_fee_amount', 36, 18)->default('0');
            $table->decimal('net_amount', 36, 18)->default('0');
            $table->string('currency', 24)->nullable();
            $table->string('asset', 24)->nullable();
            $table->string('status', 40)->default('QUOTED')->index();
            $table->string('source', 40)->default('PRICING_ENGINE');
            $table->json('context')->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('decided_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'product', 'operation']);
        });

        Schema::create('pricing_rule_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('change_uuid')->unique();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('status', 40)->default('PENDING_APPROVAL')->index();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('impact_preview')->nullable();
            $table->text('reason');
            $table->text('approval_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reward_policy_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rule_uuid')->unique();
            $table->string('name', 160);
            $table->string('product', 60)->index();
            $table->string('operation', 80)->index();
            $table->string('reward_type', 40);
            $table->decimal('value', 36, 18)->default('0');
            $table->decimal('percentage_bps', 18, 8)->default('0');
            $table->decimal('daily_user_cap', 36, 18)->nullable();
            $table->decimal('lifetime_user_cap', 36, 18)->nullable();
            $table->decimal('campaign_budget', 36, 18)->nullable();
            $table->decimal('campaign_spent', 36, 18)->default('0');
            $table->string('reward_asset', 24)->default('EXAPOINT');
            $table->string('country', 3)->nullable()->index();
            $table->string('vip_tier', 24)->nullable()->index();
            $table->string('promotion_code', 80)->nullable()->index();
            $table->integer('priority')->default(0)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 40)->default('DRAFT')->index();
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_until')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['product', 'operation', 'status'], 'reward_policy_rules_lookup');
        });

        Schema::create('reward_policy_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('decision_uuid')->unique();
            $table->foreignId('reward_policy_rule_id')->nullable()->constrained('reward_policy_rules')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product', 60)->index();
            $table->string('operation', 80)->index();
            $table->decimal('gross_amount', 36, 18)->default('0');
            $table->decimal('reward_amount', 36, 18)->default('0');
            $table->string('reward_asset', 24)->default('EXAPOINT');
            $table->string('status', 40)->default('APPROVED')->index();
            $table->string('reason_code', 120)->nullable();
            $table->json('context')->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('decided_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'product', 'operation']);
        });

        Schema::create('pricing_shadow_comparisons', function (Blueprint $table): void {
            $table->id();
            $table->uuid('comparison_uuid')->unique();
            $table->string('product', 60)->index();
            $table->string('operation', 80)->index();
            $table->decimal('legacy_fee_amount', 36, 18)->default('0');
            $table->decimal('engine_fee_amount', 36, 18)->default('0');
            $table->decimal('difference_amount', 36, 18)->default('0');
            $table->string('status', 40)->default('MATCH')->index();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_shadow_comparisons');
        Schema::dropIfExists('reward_policy_decisions');
        Schema::dropIfExists('reward_policy_rules');
        Schema::dropIfExists('pricing_rule_changes');
        Schema::dropIfExists('pricing_decisions');
        Schema::dropIfExists('pricing_rules');
    }
};
