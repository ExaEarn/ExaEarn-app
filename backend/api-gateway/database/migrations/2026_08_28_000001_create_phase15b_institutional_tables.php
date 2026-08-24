<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('application_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_company_name', 180);
            $table->string('trading_name', 160)->nullable();
            $table->string('incorporation_country', 120);
            $table->string('registration_number', 120)->nullable();
            $table->string('business_type', 80);
            $table->string('website')->nullable();
            $table->string('contact_person', 140);
            $table->string('business_email', 180);
            $table->decimal('expected_monthly_spot_volume', 36, 18)->default('0');
            $table->decimal('expected_monthly_futures_volume', 36, 18)->default('0');
            $table->decimal('expected_assets_under_custody', 36, 18)->default('0');
            $table->unsignedInteger('team_size')->default(1);
            $table->json('intended_products')->nullable();
            $table->json('api_requirements')->nullable();
            $table->boolean('market_making_interest')->default(false);
            $table->boolean('otc_interest')->default(false);
            $table->json('fiat_requirements')->nullable();
            $table->json('subaccount_requirements')->nullable();
            $table->string('state', 40)->default('APPLICATION_PENDING')->index();
            $table->string('kyb_status', 40)->default('PENDING')->index();
            $table->string('compliance_status', 40)->default('PENDING')->index();
            $table->string('risk_rating', 40)->default('UNRATED')->index();
            $table->foreignId('recommended_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'state']);
        });

        Schema::create('institutional_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('institution_uuid')->unique();
            $table->foreignId('application_id')->nullable()->constrained('institutional_applications')->nullOnDelete();
            $table->foreignId('master_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('legal_name', 180);
            $table->string('trading_name', 160)->nullable();
            $table->string('registration_number', 120)->nullable();
            $table->string('country_of_incorporation', 120);
            $table->string('business_type', 80);
            $table->string('status', 40)->default('APPROVED')->index();
            $table->string('kyb_status', 40)->default('APPROVED')->index();
            $table->string('compliance_status', 40)->default('APPROVED')->index();
            $table->string('risk_rating', 40)->default('MEDIUM')->index();
            $table->string('vip_tier', 20)->default('STANDARD')->index();
            $table->unsignedBigInteger('fee_profile_id')->nullable()->index();
            $table->foreignId('account_manager_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('capability_flags')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['master_user_id', 'status']);
        });

        Schema::create('institutional_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('role_type', 40)->default('CUSTOM');
            $table->json('permissions');
            $table->boolean('system_template')->default(false);
            $table->timestamps();
            $table->unique(['institution_id', 'name']);
        });

        Schema::create('institutional_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('membership_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('institutional_roles')->nullOnDelete();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->json('permissions_override')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'user_id']);
        });

        Schema::create('institutional_subaccounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('subaccount_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 40)->default('GENERAL')->index();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->string('risk_mode', 40)->default('ISOLATED');
            $table->json('product_flags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'name']);
            $table->index(['institution_id', 'type', 'status']);
        });

        Schema::create('institutional_member_subaccount_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained('institutional_memberships')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->string('permission', 80);
            $table->timestamps();
            $table->unique(['membership_id', 'subaccount_id', 'permission'], 'inst_member_sub_perm_unique');
            $table->index(['subaccount_id', 'permission']);
        });

        Schema::create('institutional_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transfer_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('source_subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->foreignId('destination_subaccount_id')->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset', 24);
            $table->decimal('amount', 36, 18);
            $table->string('status', 40)->default('PENDING')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->string('approval_policy', 80)->default('AUTO');
            $table->decimal('approval_threshold', 36, 18)->default('0');
            $table->string('ledger_reference', 180)->nullable()->unique();
            $table->text('reference_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'asset', 'status']);
        });

        Schema::create('institutional_transfer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id')->constrained('institutional_transfer_requests')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 40);
            $table->text('reason');
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['transfer_id', 'approver_user_id']);
        });

        Schema::create('vip_tier_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('tier', 20)->unique();
            $table->decimal('min_30d_spot_volume', 36, 18)->default('0');
            $table->decimal('min_30d_futures_volume', 36, 18)->default('0');
            $table->decimal('min_average_balance', 36, 18)->default('0');
            $table->json('benefits')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('institutional_fee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('fee_profile_uuid')->unique();
            $table->string('name', 120);
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->json('rules');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vip_tier_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->string('previous_tier', 20)->nullable();
            $table->string('automatic_tier', 20);
            $table->string('manual_override_tier', 20)->nullable();
            $table->string('contractual_tier', 20)->nullable();
            $table->string('effective_tier', 20);
            $table->string('reason', 255);
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('inputs')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'created_at']);
        });

        Schema::create('institutional_risk_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->cascadeOnDelete();
            $table->string('product', 40)->nullable();
            $table->string('market', 40)->nullable();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->json('limits');
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'subaccount_id', 'product', 'market']);
        });

        Schema::create('institutional_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('report_uuid')->unique();
            $table->foreignId('institution_id')->constrained('institutional_accounts')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 80);
            $table->string('status', 40)->default('COMPLETED')->index();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('filters')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('institutional_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('subaccount_id')->nullable()->constrained('institutional_subaccounts')->nullOnDelete();
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 140)->index();
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'action']);
        });

        Schema::table('developer_api_keys', function (Blueprint $table): void {
            if (!Schema::hasColumn('developer_api_keys', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('project_id')->constrained('institutional_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('developer_api_keys', 'subaccount_id')) {
                $table->foreignId('subaccount_id')->nullable()->after('institution_id')->constrained('institutional_subaccounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('developer_api_keys', 'rate_profile')) {
                $table->string('rate_profile', 40)->default('RETAIL')->after('environment')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            foreach (['rate_profile', 'subaccount_id', 'institution_id'] as $column) {
                if (Schema::hasColumn('developer_api_keys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('institutional_audit_events');
        Schema::dropIfExists('institutional_reports');
        Schema::dropIfExists('institutional_risk_profiles');
        Schema::dropIfExists('vip_tier_history');
        Schema::dropIfExists('institutional_fee_profiles');
        Schema::dropIfExists('vip_tier_definitions');
        Schema::dropIfExists('institutional_transfer_approvals');
        Schema::dropIfExists('institutional_transfer_requests');
        Schema::dropIfExists('institutional_member_subaccount_permissions');
        Schema::dropIfExists('institutional_subaccounts');
        Schema::dropIfExists('institutional_memberships');
        Schema::dropIfExists('institutional_roles');
        Schema::dropIfExists('institutional_accounts');
        Schema::dropIfExists('institutional_applications');
    }
};
