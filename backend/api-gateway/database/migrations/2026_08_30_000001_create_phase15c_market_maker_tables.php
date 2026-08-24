<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_maker_program_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('application_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->foreignId('applicant_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_type', 40)->default('INSTITUTIONAL_MARKET_MAKER');
            $table->string('status', 40)->default('PENDING_TECHNICAL_REVIEW');
            $table->json('requested_markets')->nullable();
            $table->json('requested_products')->nullable();
            $table->json('technical_profile')->nullable();
            $table->json('risk_profile')->nullable();
            $table->json('commercial_terms')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('recommended_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('idempotency_key', 160)->nullable()->unique();
            $table->timestamps();
            $table->index(['institution_id', 'status']);
            $table->index(['subaccount_id', 'status']);
        });

        Schema::create('market_maker_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('profile_uuid')->unique();
            $table->foreignId('application_id')->nullable()->constrained('market_maker_program_applications')->nullOnDelete();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->foreignId('fee_profile_id')->nullable()->constrained('institutional_fee_profiles')->nullOnDelete();
            $table->string('status', 40)->default('ACTIVE');
            $table->string('provider_type', 40)->default('INSTITUTIONAL_MARKET_MAKER');
            $table->string('agreement_type', 40)->default('STANDARD');
            $table->string('rate_profile', 80)->default('MARKET_MAKER_STANDARD');
            $table->string('safety_mode', 40)->default('NORMAL');
            $table->json('approved_markets')->nullable();
            $table->json('limits')->nullable();
            $table->json('risk_profile')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('subaccount_id');
            $table->index(['status', 'safety_mode']);
        });

        Schema::create('market_maker_market_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assignment_uuid')->unique();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('market_symbol', 48);
            $table->string('status', 40)->default('ACTIVE');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->decimal('minimum_depth', 36, 18)->default('0');
            $table->decimal('maximum_spread_bps', 18, 8)->default('100');
            $table->decimal('minimum_quote_presence', 18, 8)->default('95');
            $table->decimal('target_quote_size', 36, 18)->default('0');
            $table->decimal('maximum_inventory', 36, 18)->default('0');
            $table->json('rebate_profile')->nullable();
            $table->foreignId('listing_liquidity_requirement_id')->nullable()->constrained('listing_liquidity_requirements')->nullOnDelete();
            $table->json('obligations')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['market_symbol', 'status']);
            $table->index(['market_maker_id', 'status']);
        });

        Schema::create('liquidity_agreements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('agreement_uuid')->unique();
            $table->string('provider_type', 40)->default('MARKET_MAKER');
            $table->foreignId('market_maker_id')->nullable()->constrained('market_maker_profiles')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('market_symbol', 48);
            $table->string('agreement_type', 40)->default('LISTING_LIQUIDITY');
            $table->string('base_asset', 24);
            $table->string('quote_asset', 24);
            $table->decimal('base_commitment', 36, 18)->default('0');
            $table->decimal('quote_commitment', 36, 18)->default('0');
            $table->decimal('spread_requirement_bps', 18, 8)->default('100');
            $table->decimal('depth_requirement', 36, 18)->default('0');
            $table->decimal('quote_presence_requirement', 18, 8)->default('95');
            $table->json('rebate_profile')->nullable();
            $table->foreignId('fee_profile_id')->nullable()->constrained('institutional_fee_profiles')->nullOnDelete();
            $table->string('status', 40)->default('ACTIVE');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['market_symbol', 'status']);
            $table->index(['market_maker_id', 'status']);
        });

        Schema::create('market_maker_rebate_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rebate_uuid')->unique();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('market_maker_market_assignments')->nullOnDelete();
            $table->string('period_type', 24)->default('DAILY');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('eligible_maker_volume', 36, 18)->default('0');
            $table->decimal('disqualified_volume', 36, 18)->default('0');
            $table->string('rebate_asset', 24);
            $table->decimal('rebate_amount', 36, 18)->default('0');
            $table->string('status', 40)->default('ACCRUED');
            $table->string('settlement_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['market_maker_id', 'assignment_id', 'period_start', 'period_end'], 'mm_rebate_period_unique');
        });

        Schema::create('market_maker_inventory_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('snapshot_uuid')->unique();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->string('market_symbol', 48);
            $table->string('base_asset', 24);
            $table->string('quote_asset', 24);
            $table->decimal('current_base_inventory', 36, 18)->default('0');
            $table->decimal('current_quote_inventory', 36, 18)->default('0');
            $table->decimal('target_base_inventory', 36, 18)->default('0');
            $table->decimal('target_quote_inventory', 36, 18)->default('0');
            $table->decimal('inventory_imbalance', 36, 18)->default('0');
            $table->decimal('inventory_utilization', 18, 8)->default('0');
            $table->decimal('net_delta', 36, 18)->default('0');
            $table->decimal('max_exposure', 36, 18)->default('0');
            $table->string('status', 40)->default('HEALTHY');
            $table->json('metadata')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['market_symbol', 'measured_at']);
        });

        Schema::create('market_liquidity_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('snapshot_uuid')->unique();
            $table->string('market_symbol', 48);
            $table->string('status', 40);
            $table->decimal('best_bid', 36, 18)->nullable();
            $table->decimal('best_ask', 36, 18)->nullable();
            $table->decimal('spread_bps', 18, 8)->nullable();
            $table->decimal('bid_depth', 36, 18)->default('0');
            $table->decimal('ask_depth', 36, 18)->default('0');
            $table->decimal('quote_presence', 18, 8)->default('0');
            $table->unsignedInteger('market_maker_count')->default(0);
            $table->decimal('score', 18, 8)->default('0');
            $table->json('reasons')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['market_symbol', 'measured_at']);
        });

        Schema::create('market_maker_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->foreignId('market_maker_id')->nullable()->constrained('market_maker_profiles')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('market_maker_market_assignments')->nullOnDelete();
            $table->string('market_symbol', 48)->nullable();
            $table->string('category', 80);
            $table->string('severity', 24)->default('WARNING');
            $table->string('status', 40)->default('OPEN');
            $table->string('title', 180);
            $table->json('evidence')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['market_symbol', 'status']);
        });

        Schema::create('market_maker_surveillance_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('case_uuid')->unique();
            $table->foreignId('market_maker_id')->nullable()->constrained('market_maker_profiles')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->string('signal_type', 80);
            $table->string('severity', 24)->default('WARNING');
            $table->string('status', 40)->default('OPEN');
            $table->json('evidence')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['signal_type', 'status']);
        });

        Schema::create('market_maker_rebalance_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rebalance_uuid')->unique();
            $table->foreignId('market_maker_id')->nullable()->constrained('market_maker_profiles')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('source_subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->foreignId('destination_subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('asset', 24);
            $table->decimal('amount', 36, 18);
            $table->string('mode', 40)->default('INTERNAL_TRANSFER');
            $table->string('status', 40)->default('REQUESTED');
            $table->string('idempotency_key', 160)->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('market_maker_performance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('snapshot_uuid')->unique();
            $table->foreignId('market_maker_id')->constrained('market_maker_profiles')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('market_maker_market_assignments')->nullOnDelete();
            $table->string('market_symbol', 48);
            $table->decimal('maker_volume', 36, 18)->default('0');
            $table->decimal('taker_volume', 36, 18)->default('0');
            $table->decimal('spread_compliance', 18, 8)->default('0');
            $table->decimal('depth_compliance', 18, 8)->default('0');
            $table->decimal('quote_presence', 18, 8)->default('0');
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('cancel_count')->default(0);
            $table->decimal('reject_rate', 18, 8)->default('0');
            $table->decimal('rebates', 36, 18)->default('0');
            $table->decimal('fees', 36, 18)->default('0');
            $table->unsignedInteger('risk_breaches')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['market_symbol', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_maker_performance_snapshots');
        Schema::dropIfExists('market_maker_rebalance_requests');
        Schema::dropIfExists('market_maker_surveillance_cases');
        Schema::dropIfExists('market_maker_incidents');
        Schema::dropIfExists('market_liquidity_health_snapshots');
        Schema::dropIfExists('market_maker_inventory_snapshots');
        Schema::dropIfExists('market_maker_rebate_periods');
        Schema::dropIfExists('liquidity_agreements');
        Schema::dropIfExists('market_maker_market_assignments');
        Schema::dropIfExists('market_maker_profiles');
        Schema::dropIfExists('market_maker_program_applications');
    }
};
