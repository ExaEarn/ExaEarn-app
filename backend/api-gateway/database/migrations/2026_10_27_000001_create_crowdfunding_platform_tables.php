<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crowdfunding_creators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('creator_type')->default('INDIVIDUAL');
            $table->string('display_name');
            $table->string('country', 8)->nullable();
            $table->string('kyc_status')->default('PENDING');
            $table->string('kyb_status')->default('NOT_REQUIRED');
            $table->string('verification_state')->default('PENDING');
            $table->string('risk_state')->default('NORMAL');
            $table->string('status')->default('ACTIVE')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'display_name']);
        });

        Schema::create('crowdfunding_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('creator_id')->constrained('crowdfunding_creators')->cascadeOnDelete();
            $table->string('classification')->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('category')->default('General')->index();
            $table->string('asset', 24)->default('USDT')->index();
            $table->decimal('funding_goal', 36, 18);
            $table->decimal('minimum_goal', 36, 18)->default(0);
            $table->decimal('maximum_goal', 36, 18)->nullable();
            $table->decimal('minimum_pledge', 36, 18)->default(1);
            $table->decimal('maximum_pledge', 36, 18)->nullable();
            $table->decimal('raised_amount', 36, 18)->default(0);
            $table->string('status')->default('DRAFT')->index();
            $table->string('funding_model')->default('ALL_OR_NOTHING');
            $table->string('escrow_policy')->default('CANONICAL_ESCROW');
            $table->string('milestone_policy')->default('ADMIN_REVIEW');
            $table->string('refund_policy')->default('GOAL_OR_CANCELLATION');
            $table->string('risk_level')->default('NORMAL');
            $table->string('country', 8)->nullable();
            $table->string('compliance_policy_version')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['classification', 'status']);
        });

        Schema::create('crowdfunding_pledges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->foreignId('backer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 36, 18);
            $table->string('asset', 24);
            $table->json('pricing_snapshot')->nullable();
            $table->string('reservation_id')->nullable()->index();
            $table->string('ledger_reference')->nullable()->index();
            $table->string('refund_reference')->nullable()->index();
            $table->string('status')->default('PLEDGED')->index();
            $table->string('idempotency_key')->unique();
            $table->boolean('anonymous_display')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
            $table->index(['backer_id', 'created_at']);
        });

        Schema::create('crowdfunding_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 36, 18)->default(0);
            $table->decimal('release_percentage', 18, 8)->default(0);
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->boolean('evidence_required')->default(true);
            $table->json('evidence')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reference')->nullable()->index();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'sequence']);
        });

        Schema::create('crowdfunding_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('crowdfunding_creators')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('PUBLISHED')->index();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('crowdfunding_refund_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_uuid')->unique();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->string('reason');
            $table->string('status')->default('PENDING')->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('crowdfunding_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('payout_uuid')->unique();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('crowdfunding_milestones')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('crowdfunding_creators')->cascadeOnDelete();
            $table->decimal('amount', 36, 18);
            $table->string('asset', 24);
            $table->string('status')->default('PAYOUT_PENDING')->index();
            $table->string('payable_reference')->nullable()->index();
            $table->string('payout_reference')->nullable()->index();
            $table->foreignId('maker_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('checker_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('crowdfunding_reconciliation_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('crowdfunding_campaigns')->nullOnDelete();
            $table->string('incident_type');
            $table->string('severity')->default('MEDIUM');
            $table->string('status')->default('OPEN')->index();
            $table->json('evidence')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'crowdfunding_reconciliation_incidents',
            'crowdfunding_payouts',
            'crowdfunding_refund_batches',
            'crowdfunding_updates',
            'crowdfunding_milestones',
            'crowdfunding_pledges',
            'crowdfunding_campaigns',
            'crowdfunding_creators',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
