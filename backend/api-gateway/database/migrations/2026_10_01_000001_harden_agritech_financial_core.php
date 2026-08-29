<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farming_projects', function (Blueprint $table): void {
            $table->string('product_type', 48)->default('FARM_PROJECT')->index();
            $table->string('economic_type', 48)->default('NON_INVESTMENT_SUPPORT')->index();
            $table->string('currency', 16)->default('USDT');
            $table->string('legal_status', 32)->default('PENDING_REVIEW')->index();
            $table->string('verification_status', 32)->default('UNVERIFIED')->index();
            $table->boolean('public_funding_enabled')->default(false)->index();
            $table->timestamp('funding_deadline')->nullable()->index();
            $table->jsonb('risk_disclosures')->nullable();
            $table->jsonb('settlement_policy')->nullable();
        });

        Schema::table('farm_shares', function (Blueprint $table): void {
            $table->unsignedBigInteger('shares_reserved')->default(0);
            $table->unsignedBigInteger('shares_allocated')->default(0);
        });

        Schema::table('farm_investments', function (Blueprint $table): void {
            $table->string('idempotency_key', 180)->nullable()->unique();
            $table->uuid('reservation_id')->nullable()->index();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('pricing_decision_id')->nullable()->constrained('pricing_decisions')->nullOnDelete();
            $table->string('asset', 16)->default('USDT');
            $table->string('financial_status', 32)->default('PENDING')->index();
            $table->jsonb('compliance_snapshot')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::table('farmers', function (Blueprint $table): void {
            $table->string('state', 32)->default('APPLIED')->index();
            $table->string('identity_status', 32)->default('PENDING')->index();
            $table->string('land_verification_status', 32)->default('PENDING')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
        });

        Schema::table('produce_tracking', function (Blueprint $table): void {
            $table->decimal('verified_yield', 30, 18)->nullable();
            $table->string('evidence_source', 48)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
        });

        Schema::create('agri_project_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->string('evidence_type', 48);
            $table->string('source_type', 48);
            $table->string('status', 32)->default('PENDING_REVIEW')->index();
            $table->string('storage_reference', 512)->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->decimal('confidence', 8, 6)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'evidence_type', 'status']);
        });

        Schema::create('agri_project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('release_amount', 30, 18)->default(0);
            $table->string('asset', 16)->default('USDT');
            $table->timestamp('target_at')->nullable();
            $table->string('status', 32)->default('PENDING')->index();
            $table->boolean('evidence_required')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('agri_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('disbursement_uuid')->unique();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('agri_project_milestones')->nullOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->decimal('amount', 30, 18);
            $table->string('asset', 16);
            $table->string('status', 32)->default('PENDING_APPROVAL')->index();
            $table->string('idempotency_key', 180)->unique();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('agri_harvest_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('settlement_uuid')->unique();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->string('period_key', 64);
            $table->string('status', 32)->default('PENDING_VERIFICATION')->index();
            $table->string('revenue_source_type', 48);
            $table->string('revenue_reference', 255);
            $table->decimal('gross_revenue', 30, 18);
            $table->decimal('verified_costs', 30, 18)->default(0);
            $table->decimal('platform_fee', 30, 18)->default(0);
            $table->decimal('net_distributable', 30, 18)->default(0);
            $table->string('asset', 16);
            $table->string('idempotency_key', 180)->unique();
            $table->foreignId('revenue_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'period_key']);
        });

        Schema::create('agri_investor_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('harvest_settlement_id')->constrained('agri_harvest_settlements')->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained('farm_investments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('gross_amount', 30, 18);
            $table->decimal('fee_amount', 30, 18)->default(0);
            $table->decimal('net_amount', 30, 18);
            $table->string('asset', 16);
            $table->string('status', 32)->default('PAYABLE')->index();
            $table->string('allocation_version', 32);
            $table->string('idempotency_key', 180)->unique();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['harvest_settlement_id', 'investment_id']);
        });

        Schema::create('agri_reconciliation_findings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('finding_uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained('farming_projects')->nullOnDelete();
            $table->string('finding_type', 64)->index();
            $table->string('severity', 16)->default('HIGH');
            $table->string('status', 24)->default('OPEN')->index();
            $table->text('description');
            $table->jsonb('expected')->nullable();
            $table->jsonb('actual')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agri_reconciliation_findings');
        Schema::dropIfExists('agri_investor_allocations');
        Schema::dropIfExists('agri_harvest_settlements');
        Schema::dropIfExists('agri_disbursements');
        Schema::dropIfExists('agri_project_milestones');
        Schema::dropIfExists('agri_project_evidence');

        Schema::table('produce_tracking', function (Blueprint $table): void {
            $table->dropColumn(['verified_yield', 'evidence_source', 'verified_by', 'verified_at']);
        });
        Schema::table('farmers', function (Blueprint $table): void {
            $table->dropColumn(['state', 'identity_status', 'land_verification_status', 'reviewed_by', 'reviewed_at', 'review_reason']);
        });
        Schema::table('farm_investments', function (Blueprint $table): void {
            $table->dropColumn(['idempotency_key', 'reservation_id', 'ledger_transaction_id', 'pricing_decision_id', 'asset', 'financial_status', 'compliance_snapshot', 'settled_at', 'cancelled_at']);
        });
        Schema::table('farm_shares', function (Blueprint $table): void {
            $table->dropColumn(['shares_reserved', 'shares_allocated']);
        });
        Schema::table('farming_projects', function (Blueprint $table): void {
            $table->dropColumn(['product_type', 'economic_type', 'currency', 'legal_status', 'verification_status', 'public_funding_enabled', 'funding_deadline', 'risk_disclosures', 'settlement_policy']);
        });
    }
};
